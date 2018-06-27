#!/usr/bin/env python
# -*- coding: utf-8 -*-

import sys, json


class PyMethods:

    def __init__(self):
        pass

    def test(self, data):
        print json.dumps(data)


if __name__ == '__main__':
    try: array = json.loads(sys.argv[1])
    except: print json.dumps({'ERROR':'ERROR'})

    obj = PyMethods()
    if array['method'] == 'test': obj.test(array['data'])
