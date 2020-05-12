# coding=utf-8
import json
import Util
import Info
from flask import abort
from flask import request
from flask import jsonify
from Info.database import *
from flask import current_app as app


@Info.info_blue.route("/service")
def service_info():
    conn = app.mysql_pool.connection()
    data = {
        "status": "success",
        "version": app.config["BASE"]["version"],
        "service_state": read_kvdb(conn, "service_state"),
        "service_notice": read_kvdb(conn, "service_notice"),
        "data_time": read_kvdb(conn, "data_time")
    }
    return Util.common_rsp(data)


@Info.info_blue.route('/sentry-debug')
def sentry_debug():
    Util.print_red("Test sentry: {}".format(1 / 0), tag="DEBUG")
    return Util.common_rsp("DEBUG")


@Info.info_blue.route('/spare-room')
def spare_room():
    week = request.args.get("week")
    session = request.args.get("session")
    if week is None:
        return abort(400)
    if session is None:
        return abort(400)

    week = int(week)
    week = max(1, week)
    week = min(week, 20)

    conn = app.mysql_pool.connection()
    spare_room_list = read_spare_room(conn, week, session)

    for room in spare_room_list:
        room["info"] = room.pop("week%s" % week)
        room["info"] = json.loads(room["info"])

    res = {
        "status": "success",
        "room_list": spare_room_list
    }

    return jsonify(res)
