<?php 

	function watu_install(){

		global $wpdb;
		$table_urls=$wpdb->prefix."urls";
		$table_dlink=$wpdb->prefix."dlink";
		$table_localpath=$wpdb->prefix."localpath";

		require_once(ABSPATH."/wp-admin/upgrade.php");

		if($wpdb->get_var("SHOW TABLES LIKE '$table_urls'")!=$table_urls){
			$create_table_sql="CREATE TABLE $table_urls (
														id bigint(20) not null auto_increment PRIMARY KEY,
														url varchar(255) not null,
														log int(1) not null,
														domain varchar(255) not null,
														referer varchar(255) not null
														);";
		}
		dbDelta($create_table_sql);
		unset($create_table_sql);

		if($wpdb->get_var("SHOW TABLES LIKE '$table_dlink'")!=$table_dlink){
			$create_table_sql="CREATE TABLE $table_dlink (
														id bigint(20) not null auto_increment PRIMARY KEY,
														title varchar(255) not null,
														dlink varchar(255) not null,
														log int(1) not null,
														domain varchar(255) not null,
														referer varchar(255) not null,
														keywords varchar(255) not null
														);";
		}
		dbDelta($create_table_sql);
		unset($create_table_sql);

		if($wpdb->get_var("SHOW TABLES LIKE '$table_localpath'")!=$table_localpath){
			$create_table_sql="CREATE TABLE $table_localpath (
														id bigint(20) not null auto_increment PRIMARY KEY,
														title varchar(255) not null,
														localpath varchar(255) not null,
														log int(1) not null,
														downloadtime varchar(255) not null,
														keywords varchar(255) not null
														);";
		}
		dbDelta($create_table_sql);
		unset($create_table_sql);

		//初始化程序的参数
		update_option('watu_version','0.9.1');
		update_option('watu_translate','disable');
		update_option('watu_rest','3');
		update_option('watu_window_size','15');
		update_option('watu_p_window_size','15');
		update_option('watu_filter_st','3');
		update_option('watu_filter_zt','15');
	}

	function watu_remove(){

		wp_clear_scheduled_hook('setup_post_hook');
		delete_option('watu_version');
		delete_option('watu_rest');
		delete_option('watu_p_window_size');
		delete_option('watu_window_size');
		delete_option('watu_schedule');
		delete_option('watu_schedule_lasttime');
		delete_option('watu_category');
		delete_option('watu_filter_st');
		delete_option('watu_filter_zt');
		delete_option('watu_filter_keywords');
		delete_option('watu_translate');


	}

?>