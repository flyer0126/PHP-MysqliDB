<?php
/**
 * MysqliDB class
 *
 * @author flyer0126
 * @since 2013/09
 *
 */
class MysqliDB {
    /**
     * MysqliDB 对象数组
     * @var unknown
     */
    private static $_instance = array();

    /**
     * MysqliDB::$mysqli 对象数组
     * @var unknown
     */
    private static $_instance_mysqli = array();

    /**
     * Mysqli 对象（an object which represents the connection to a MySQL Server）
     * @var unknown
     */
    private static $mysqli = null;

    /**
     * MysqliDB 数据源类型
     * @var unknown
     */
    private static $type = null;

    /**
     * 当前执行sql 语句
     * @var unknown
     */
    private static $sql = '';

    /**
     * 当前结果集
     * @var unknown
     */
    private static $result = false;

    /**
     * 设置是否开启事务处理
     * @var unknown
     */
    private static $isCommit = false;

    /**
     * 是否存在执行错误
     * @var unknown
     */
    private static $isError = false;

    /**
     * 设置是否记录错误信息
     * @var unknown
     */
    public static $isLogError = true;

    /**
     * 设置是否记录错误信息
     * @var unknown
     */
    public static $debug = true;

    /**
     * 利用单例模式获取MysqliDB对象
     * @param boolean $is_master_db 是否为主库
     * @return unknown
     */
    static function getInstance($type = 'default', $mysqli = null) {
        if(isset(self::$_instance[$type]) && self::$_instance[$type]) {
            self::$mysqli = self::$_instance_mysqli[$type];
        } else {
            $obj = new self($type);
            self::$_instance[$type] = $obj;
            self::$_instance_mysqli[$type] = $obj::$mysqli;
        }

        self::$type = $type;
        return self::$_instance[$type];
    }

    /**
     * MysqliDB初始化
     * @param boolean $type  是否为主库
     * @return unknown
     */
    private function __construct($type = 'default') {

        $dbConfig = DATABASE_CONFIG::$$type;
        if ($dbConfig) {
            $host 	= $dbConfig['host'];
            $user 	= $dbConfig['username'];
            $passwd = $dbConfig['password'];
            $dbname = $dbConfig['database'];
            $port 	= $dbConfig['port'];
        }

        self::$mysqli = new mysqli($host, $user, $passwd, $dbname, $port);
        if(mysqli_connect_errno()) {
            $error = sprintf("Database Connect failed: %s\r\n", mysqli_connect_error());
            self::log($error);
            return false;
        } else {
            self::$mysqli->query("SET character_set_connection=".$dbConfig['encoding'].", character_set_results=".$dbConfig['encoding'].", character_set_client=binary");
        }

        return self::$mysqli;
    }

    /**
     * 新增操作
     * @param string $tblName  表名
     * @param array() $data	 新增数据数组
     * @return boolean|Ambigous <boolean, unknown>
     */
    function insert($tblName = null, $data = null) {
        if (!strlen(trim($tblName)) || !is_array($data) || count($data) < 1) {
            return false;
        }

        $strInsertKeys = '';
        $strInsertValues = '';
        foreach ($data as $key => $val) {
            self::formatValue($val);
            $strInsertKeys .= $strInsertKeys ? ", `{$key}`" : "`{$key}`";
            $strInsertValues .= $strInsertValues ? ", '" . $val . "'" : "'" . $val . "'";
        }

        $sqlInsert = "INSERT INTO `{$tblName}` ({$strInsertKeys}) VALUES ({$strInsertValues})";
        self::_query($sqlInsert);
        return self::_setResult();
    }


    /**
     * 更新操作
     * @param string $tblName  表名
     * @param array() $data  更新数据数组
     * @param string/array $where  条件
     * @return boolean|Ambigous <boolean, unknown>
     */
    function update($tblName = null, $data = null, $where = null) {
        if (!strlen(trim($tblName)) || !is_array($data) || count($data) < 1) {
            return false;
        }

        // 处理$data数据
        $strUpdate = '';
        foreach ($data as $key => $val) {
        	self::formatValue($val);
        	if (!$strUpdate)
        		$strUpdate .= "`{$key}` = '{$val}'";
        	else
        		$strUpdate .= ", `{$key}` = '{$val}'";
        }

        // 处理$where数据
        $whereStr = '';
        if (is_array($where) && count($where) > 0) {
            foreach ($where as $whereItem){
                $whereStr .= $whereStr ? " AND {$whereItem} " : "WHERE " . $whereItem;
            }
        } elseif ($where) {
            $whereStr = "WHERE " . $where;
        }

        $sqlUpdate = "UPDATE `{$tblName}` SET {$strUpdate} {$whereStr}";
        self::_query($sqlUpdate);
        return self::_setResult();
    }

    /**
     * 查询操作
     * @param string $tblName  表名
     * @param string/array $where  条件
     * @param string $order  排序
     * @param int $p  当前页
     * @param int $pcount  页数目
     * @param string/array $fields  字段域
     * @return boolean|multitype:
     */
    function select($tblName = null, $where = null, $p = 0, $pcount = 0, $order = null, $fields = null) {
        if (!strlen(trim($tblName))) {
            return false;
        }

        // 处理$limit数据
        $limit = '';
        if ($p > 0 && $pcount > 0) {
            $limit = 'LIMIT ' . ($p-1) * $pcount . ', ' . $pcount;
        }
        // 处理$where数据
        $whereStr = '';
        if (is_array($where) && count($where) > 0) {
            foreach ($where as $whereItem){
                $whereStr .= $whereStr ? " AND {$whereItem} " : 'WHERE ' . $whereItem;
            }
        } elseif ($where) {
            $whereStr = 'WHERE ' . $where;
        }
        // 处理$order数据
        $orderStr = '';
        if ($order) {
            $orderStr = 'ORDER BY '  . $order;
        }
        // 处理$fields数据
        if ($fields) {
            $fieldsStr = '';
            if (is_array($fields)){
                $fieldsStr = implode(',', $fields);
            }else{
                $fieldsStr = $fields;
            }
            $sqlGet = "SELECT {$fieldsStr} FROM {$tblName} {$whereStr} {$orderStr} {$limit}";
        } else {
            $sqlGet = "SELECT * FROM {$tblName} {$whereStr} {$orderStr} {$limit}";
        }
        self::_query($sqlGet);
        return self::_setResult();
    }

    /**
     * 多表联合查询
     * @param string $mainTable 主表
     * @param string $auxiliaryTable  辅表
     * @param string/array $where  条件
     * @param number $limit  限制数
     * @param string/array $fields 字段域
     * @param string $order 排序
     * @return boolean
     *
     * demo:
     * $ret = $db->getMultiTable(
     *		'tb_student',
     *		'tb_student_card',
     *		array('tb_student.stu_card_id = tb_student_card.sc_id'),
     *		10,
     *		array('tb_student.stu_uid', 'tb_student.stu_name', 'tb_student.stu_grade_id', 'tb_student_card.sc_cardno'),
     *		'tb_student.stu_uid desc'
     *	);
     * //SELECT
     *	  tb_student.stu_uid,
     *	  tb_student.stu_name,
     *	  tb_student.stu_grade_id,
     *	  tb_student_card.sc_cardno
     *	FROM tb_student,
     *	  tb_student_card
     *	WHERE tb_student.stu_card_id = tb_student_card.sc_id
     *	ORDER BY tb_student.stu_uid DESC
     *	LIMIT 10;
     */
    function multiTableQuery($mainTable = null, $auxiliaryTable = null, $where = null, $limit = 0, $fields = null, $order = null) {
        if (!strlen(trim($mainTable))) {
            return false;
        }

        $tableStr = '';
        if (trim($auxiliaryTable)) {
            $tableStr = "{$mainTable}, {$auxiliaryTable}";
        }
        // 处理$where数据
        $whereStr = '';
        if (is_array($where) && count($where) > 0) {
            foreach ($where as $whereItem){
                $whereStr .= $whereStr ? " AND {$whereItem} " : 'WHERE ' . $whereItem;
            }
        } elseif ($where) {
            $whereStr = 'WHERE ' . $where;
        }
        // 处理$order数据
        $orderStr = '';
        if ($order) {
            $orderStr = 'ORDER BY ' . $order;
        }
        // 处理$limit数据
        $limitStr = '';
        if ($limit > 0) {
            $limitStr = 'LIMIT ' . $limit;
        }
        // 处理$fields数据
        if ($fields) {
            $fieldsStr = '';
            if (is_array($fields)){
                $fieldsStr = implode(',', $fields);
            }else{
                $fieldsStr = $fields;
            }
            $sqlMultiGet = "SELECT {$fieldsStr} FROM {$tableStr} {$whereStr} {$orderStr} {$limitStr}";
        } else {
            $sqlMultiGet = "SELECT * FROM {$tableStr} {$whereStr} {$orderStr} {$limitStr}";
        }

        self::_query($sqlMultiGet);
        return self::_setResult();
    }

    /**
     * 删除操作
     * @param string $tblName  表名
     * @param string $where  条件
     * @return boolean
     */
    function delete($tblName = null, $where = null) {
        if (!strlen(trim($tblName))) {
            return false;
        }

        // 处理$where数据
        $whereStr = '';
        if (is_array($where) && count($where) > 0) {
            foreach ($where as $whereItem){
                $whereStr .= $whereStr ? " AND {$whereItem} " : "WHERE " . $whereItem;
            }
        } elseif ($where) {
            $whereStr = "WHERE " . $where;
        }

        $sqlDelete = "DELETE FROM {$tblName} {$whereStr};";
        self::_query($sqlDelete);
        return self::_setResult();
    }

    /**
     * sql执行操作
     * @param string $sql  sql语句
     * @return boolean
     */
    public function query($sql = null) {
        if (!strlen(trim($sql))) {
            return false;
        }

        self::_query($sql);
        return self::_setResult();
    }

	/**
	 * 处理结果集
	 * desc:查询时返回结果集，增、删、改时返回sql执行状态（bool值）
	 * @return multitype:unknown
	 */
	private static function _setResult() {
		if (is_bool(self::$result)) {
			return self::$result;
		} elseif (is_object(self::$result)) {
			$result = array();
			while ($row = self::$result->fetch_assoc()) {
				$result[] = $row;
			}
			return $result;
		}
	}
	
	/**
	 * sql执行操作
	 * @param string $sql  sql语句
	 * @return boolean
	 */
	private static function _query($sql = null) {
		if (!strlen(trim($sql))) {
			return false;
		}
		
		self::$sql = $sql;
		$timeStart = self::microtimeFloat();
		self::$result = self::$mysqli->query($sql);
		if(self::$mysqli->error){
			$error = sprintf("SQL Query Error: %s\r\n", self::$mysqli->error);
			self::$isError = true;
			self::log($error);
			return false;
		}
		
		// 性能分析
		self::sqlAdd(self::microtimeFloat() - $timeStart);
	}
	
	/**
	 * 格式化值
	 * @param string $value 待格式化的字符串,格式成可被数据库接受的格式
	 */
	private static function formatValue(&$value) {
		$value = mysql_escape_string(trim($value));
	}
	
	/**
	 * 获取最大记录ID值
	 */
	public function getLastInsertID(){
		return self::$mysqli->insert_id;
	}
	
	/**
	 * 获取记录数目
	 * @param string $tblName  表名
	 * @param string/array $where  条件
	 * @param string $order  排序
	 * @return boolean|number
	 */
	function findCount($tblName = null, $where = null, $order = null) {
		if (!strlen(trim($tblName))) {
			return false;
		}
		
		// 处理$where数据
		$whereStr = '';
		if (is_array($where) && count($where) > 0) {
			foreach ($where as $whereItem){
				$whereStr .= $whereStr ? " AND {$whereItem} " : 'WHERE ' . $whereItem;
			}
		} elseif ($where) {
			$whereStr = 'WHERE ' . $where;
		}
		// 处理$order数据
		$orderStr = '';
		if ($order) {
			$orderStr = 'ORDER BY'  . $order;
		}
		
		// 处理查询
		$sql_count = "SELECT count(*) AS count FROM {$tblName} {$whereStr} {$orderStr}";
		self::_query($sql_count);
		if(!empty(self::$result)){
			$row = self::$result->fetch_array();
			return $row[0];
		}else{
			return 0;
		}
	}
	
	/**
	 * 释放数据集
	 * @param string $result
	 */
	private static function freeResult() {
		@self::$result->free();
	}
	
	/**
	 * 返回当前sql 语句
	 * @return string
	 */
	public function getSql() {
		return self::$sql;
	}
	
	/**
	 * 返回当前sql 执行影响记录数
	 * @return string
	 */
	public function getAffectedRows() {
		return self::$mysqli->affected_rows;
	}
	
	/**
	 * commit_begin()
		if(db::$isError) rollback()
	   commit_end()
	 */
	
	/**
	 * 开启事务处理，关闭Mysql的自动提交模式
	 */
	public function begin() {
		self::$isError = false;
		
		// set autocommit to off
		self::$mysqli->autocommit(false);
		
		self::$isCommit = true;
	}
	
	/**
	 * 提交事务处理
	 */
	public function commit() {
		if(self::$isCommit) {
			// 初始化设置
			// set autocommit to on
			self::$mysqli->autocommit(true);
			self::$isCommit = false;
			self::$isError = false;
			
			// commit transaction
			return self::$mysqli->commit();
		}
	}
	
	/**
	 * 回滚事务处理
	 */
	public function rollback() {
		self::$mysqli->rollback();
	}
	
	/**
	 * 日志处理
	 * @param string $message // 日志消息
	 * @return boolean
	 */
	private static function log($message = null) {
		// 消息为空，直接返回
		if (!strlen(trim($message))) {
			return false;
		}
		
		// 处理消息内容
		$message = $message . self::$sql . '-->' . date('Y-m-d H:i:s') . "\r\n";
		// 写入日志消息
		if(self::$isLogError){
			$file = dirname(dirname(__FILE__)).'/logs/db_error.log';
			
			// 文件不存在时尝试创建
			if (!file_exists($file)) {
				$fp=fopen($file, "w+");
				fclose($fp);
			}
			// 文件是否可写
			if (!is_writable($file)) {
				chmod($file, '777');
			}
			// 使用添加模式打开$file
			if (!$handle = fopen($file, 'a')) {
				throw new Exception("不能打开文件 $file");
				exit;
			}
			// 将$message写入文件
			if (fwrite($handle, $message) === FALSE) {
				throw new Exception("不能写入到文件 $file");
				exit;
			}
			
			fclose($handle);
		}
		
		return true;
	}
	
	############################### 性能分析 start #################################
	/**
	 * 初始化脚本开始执行时间
	 * @return number
	 */
	private static function microtimeFloat() {
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
	
	/**
	 * sql执行信息添加
	 * @param date $time  消耗时间
	 */
	private static function sqlAdd($time = null) {
		global $sql_execute_list;
		if (self::$debug) {
			$sql_execute_list[self::$sql] = $time;
		}
	}
	
	/**
	 * sql及执行时间展示
	 */
	public function debugShow() {
		global $sql_execute_list;
		echo  "<!-- explain::sql_execute_list() -->\n";
		if (is_array($sql_execute_list) && !empty($sql_execute_list)) {
			echo "<table  style='float:left' width=100% border=1>\n";
			foreach ($sql_execute_list as $sql => $time) {
				echo "<tr><td style='background-color:red'>{$sql}</td><td>--{$time}</td></tr>\n";
			}
			echo "</table>\n";
		}
	}
	
	############################### 性能分析 end #################################
	
}
