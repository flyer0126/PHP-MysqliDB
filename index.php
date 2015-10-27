<?php
/**
 * This is database configuration file.
 *
 * @author flyer0126
 * @since  2013/09
 */

// 引入文件
include_once "database.php";
include_once "MysqliDB.php";

$defaultdb = MysqliDB::getInstance();
$getSql = "select * from table where id = 1";
$rlt = $defaultdb->query($getSql);
