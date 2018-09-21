# php-migrations
Collection of php migrations which may be useful in some cases

### dump_database_with_env_changing.php
- creates current using database dump
- locks newly created dump database for WRITE
- switches .env variables for allowing newly incoming requests to hang within LOCK
- copies old database data within new db
- releases LOCK from newly created db for allowing users properly processing