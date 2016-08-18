<?php

se_db_query("ALTER TABLE company_person
  ADD CONSTRAINT FK_company_person_company_id FOREIGN KEY (id_company)
    REFERENCES company(id) ON DELETE CASCADE ON UPDATE RESTRICT");

se_db_query("ALTER TABLE company_person
  ADD CONSTRAINT FK_company_person_se_user_id FOREIGN KEY (id_person)
    REFERENCES se_user(id) ON DELETE CASCADE ON UPDATE RESTRICT");
