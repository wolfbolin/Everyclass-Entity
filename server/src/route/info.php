<?php
/**
 * Created by PhpStorm.
 * User: wolfbolin
 * Date: 2019/3/11
 * Time: 14:55
 */

use \Slim\App;
use \Slim\Http\Request;
use \Slim\Http\Response;

$app->get('/', function (Response $response) {
    $result = ['status' => 'success', 'info' => 'Hello, world!'];
    return $response->withJson($result);
});

$app->group('/info', function (App $app) {
    $app->get('/service', function (Response $response) {
        // 获取协议版本
        $version = $this->get('Version');
        // 获取服务状态与服务描述
        $db = new \MongoDB\Database($this->get('mongodb_client'), $this->get('MongoDB')['entity']);
        $collection = $db->selectCollection('info');
        $service_state = $collection->findOne([
            'key' => 'service_state'
        ]);
        $service_notice = $collection->findOne([
            'key' => 'service_notice'
        ]);
        // 获取数据更新时间
        $db = new MongoDB\Database($this->get('mongodb_client'), $this->get('MongoDB')['entity']);
        $collection = $db->selectCollection('info');
        $select_result = $collection->findOne(
            ['key' => 'update_time']
        );

        // 组织响应数据
        $result = [
            'status' => 'success',
            'version' => $version,
            'service_state' => $service_state['value'],
            'service_notice' => $service_notice['value'],
            'data_time' => $select_result['value']
        ];
        return $response->withJson($result);
    });

    $app->get('/health', function (Response $response) {
        // 初始化健康检查列表
        $check_list = $this->get('Check_list');

        // MongoDB链接检查
        try {
            $db = new MongoDB\Database($this->get('mongodb_client'), $this->get('MongoDB')['entity']);
            $collection = $db->selectCollection('info');
            $select_result = $collection->findOne(
                ['key' => 'check_code'],
                ['projection' => ['_id' => 0]]
            );
            if ($select_result['value'] == $this->get('Mongo_Token')) {
                $check_list['Mongo connection'] = true;
            }
        } catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
            $check_list['Mongo connection'] = false;
        } catch (MongoDB\Driver\Exception\AuthenticationException $e) {
            $check_list['Mongo connection'] = false;
        }

        // MySQL链接检查
        $db = new mysqli($this->get('MySQL')['host'] . ':' . $this->get('MySQL')['port'],
            $this->get('MySQL')['username'], $this->get('MySQL')['password']);
        if (!mysqli_connect_errno()) {
            if ($db->select_db($this->get('MySQL')['entity'])) {
                $sql = "SELECT `value` FROM `info` WHERE `key`='check_code';";
                if ($result = $db->query($sql)) {
                    $result = $result->fetch_row();
                    if ($result && $result[0] == $this->get('MySQL_Token')) {
                        $check_list['MySQL connection'] = true;
                        goto Next;
                    }
                }
            }
        }
        $check_list['MySQL connection'] = false;
        Next:

        // 反馈检查结果
        $result = [
            'status' => 'success',
            'time' => time()
        ];
        foreach ($check_list as $key => $value) {
            if ($value == false) {
                $result['status'] = 'error';
                break;
            }
        }
        $result = array_merge($result, $check_list);
        return $response->withJson($result);
    });

})->add(\WolfBolin\Slim\Middleware\x_auth_token())
    ->add(\WolfBolin\Slim\Middleware\maintenance_mode())
    ->add(\WolfBolin\Slim\Middleware\access_record());


