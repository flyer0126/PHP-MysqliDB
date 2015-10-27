<?php
/**
 * This is database configuration file.
 *
 * @author flyer0126
 * @since  2015/09
 */
class DATABASE_CONFIG {

	/**
	 * @var array 默认库
	 */
	public static $default = array(
			'datasource' => 'mysql',
			'host' => '10.1.250.1',
			'port' => 3306,
			'username' => 'user',
			'password' => 'pass',
			'database' => 'defaultdb',
			'encoding' => 'utf8'
	);
	
	/**
	 * demo0 数据库
	 * @var unknown
	 */
	public static $demo0 = array(
			'datasource' => 'mysql',
			'host' => '10.1.250.2',
			'port' => 3306,
			'username' => 'user',
			'password' => 'pass',
			'database' => 'demo0db',
			'encoding' => 'utf8'
	);

    /**
     * demo1 数据库
     * @var unknown
     */
    public static $demo1 = array(
        'datasource' => 'mysql',
        'host' => '10.1.250.3',
        'port' => 3306,
        'username' => 'user',
        'password' => 'pass',
        'database' => 'demo1db',
        'encoding' => 'utf8'
    );

}
