<?php

se_db_query("INSERT INTO permission_object(id, code, name) VALUES (18, 'payments', 'Платежи');");

se_db_query("INSERT INTO permission_object_role(id_object, id_role, mask) VALUES (18, 1, 8);");