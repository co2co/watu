<?php
	
	require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

	
	function moretimes(){
		return array( 'halfhour' =>array('interval' =>'1800' ,'display' => '每半小时一次' )
					);
	}

	add_filter('cron_schedules','moretimes');
	

	function setup_post_hook(){

		if(!wp_next_scheduled('setup_post_hook')){
			wp_schedule_event( time(),'halfhour','setup_post_hook' );
		}
	}

	add_action('setup_post_hook','mypostschedule');

	function mypostschedule(){

		$schedule_actime=date("Y-m-d H:i:s");
		update_option('watu_schedule_lasttime',$schedule_actime); //这样就知道上次执行计划任务的时间了

		$schedulelist_temp=get_option('watu_schedule');
		if($schedulelist_temp!=""){
			if(strstr($schedulelist_temp,',')){
					$schedulelist_temp_array=array_unique(explode(',', $schedulelist_temp));
					$i=1;
					foreach ($schedulelist_temp_array as $key => $value) {
						$schedule_list[$i]="http://".trim($value);
						$i++;
					}
					unset($i);
			}
			else{
				$schedule_list[1] = "http://".trim($schedulelist_temp);
			}

			//初始化定时任务的参数
			$category=get_option('watu_category');
			$bugstr = '';
			$fixstr = '';
			$proxy = '';
			$x = '1';
			$y = '0';
			//加载最重要的核心curl类文件
			require_once('watu_function.php');
			$post = new curl();
			//遍历定时任务的URL开始采集
			foreach ($schedule_list as $key => $value) {
				$url = $value;
				$post->get_request($url,$bugstr,$fixstr,$proxy,$x,$y,$category);
			}
		}
	}

	if(!wp_get_schedule('setup_post_hook')){
		setup_post_hook();
	}

?>