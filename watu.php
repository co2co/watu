<?php

	/*
	Plugin Name:挖图
	Plugin URI:www.co2co.net
	Description:按照规定的时间采集指定论坛的主题并把图片本地化再发布博文
	Version:0.9.1
	Author:co2co
	Author Emeail:co2co@foxmail.com
	*/

	require('watu_install_remove.php');
	require('watu_htmlpage.php');

	register_activation_hook(__FILE__,'watu_install');
	register_deactivation_hook(__FILE__,'watu_remove');

	require_once('watu_schedule.php');  //看来定时任务得在插件首页写代码或者挂载才行，在子页面挂载死活不行，F!
	
?>