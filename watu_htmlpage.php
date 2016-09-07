<?php
	
	add_action('admin_menu','display_watu_menu');

	function display_watu_menu(){
		add_menu_page('挖图程序设置页面','挖图','administrator',__FILE__,'watu_common_options_htmlpage');
		add_submenu_page(__FILE__,'日常采集任务页面','日常采集','administrator','general_mission_setting','general_mission_htmlpage');
		add_submenu_page(__FILE__,'定时采集任务页面','定时采集','administrator','schedule_mission_setting','schedule_mission_htmlpage');
		add_submenu_page(__FILE__,'卸载挖图','卸载挖图','administrator','uninstall','uninstall_htmlpage');
	}

	function watu_common_options_htmlpage(){

?>
		<div name="watuoptions" align="left">
			<form action="" method="post" name="watuoptions">
			===============WaTu（挖图）===============<br />
			作者：co2co<br />
			邮箱：co2co@foxmail.com<br />
			Version:<?php echo get_option('watu_version'); ?>内测版<br />
			<br />
			Notices:<br />
			以下设定为本插件核心curl类所必须使用到的参数<br />
			==============================================================================================<br />
			是否翻译标题和关键词（Keywords）：
			<?php
				if(get_option('watu_translate')=='enable'){
			?>
					<label><input type="radio" name="translate" value="enable" checked="checked">Enable</label>
			<?php
				}
				else{
			?>
					<label><input type="radio" name="translate" value="enable">Enable</label>
			<?php
				}
			
				if(get_option('watu_translate')=='disable'){
			?>
					<lable><input type="radio" name="translate" value="disable" checked="checked">Disable</label>
			<?php
				}
				else{
			?>
					<lable><input type="radio" name="translate" value="disable">Disable</label>
			<?php
				}
			?>
			当前设定：
			<?php 
				if(get_option('watu_translate')=='enable') {
					echo "需要翻译";
				}
				elseif (get_option('watu_translate')=='disable') {
					echo "不用翻译";
				}
				else{
					echo "未设置！";
				}
			?><br />
			中断秒数（防被Bank）：<input type="text" name="rest" value="<?php echo get_option('watu_rest'); ?>"><br />
			页面分析进程数：<input type="text" name="p_window_size" value="<?php echo get_option('watu_p_window_size'); ?>"><br />
			图片下载进程数：<input type="text" name="window_size" value="<?php echo get_option('watu_window_size'); ?>"><br />
			定义散图数量：<input type="text" name="filter_st" value="<?php echo get_option('watu_filter_st'); ?>"><br />
			定义杂图数量：<input type="text" name="filter_zt" value="<?php echo get_option('watu_filter_zt'); ?>"><br />
			过滤关键词（主题含有以下关键词则不发表，用英文逗号隔开）：<br />
			<textarea name="filter_keywords" rows="20" cols="40"><?php echo get_option('watu_filter_keywords');  ?></textarea><br />
			<input type="submit" value="提交">
			</form>
		</div>

<?php
			if( $_REQUEST['rest'] && $_REQUEST['p_window_size']!="" && $_REQUEST['window_size']!="" && $_REQUEST['filter_st']!="" && $_REQUEST['filter_zt']!="" && $_REQUEST['translate']!=""){
				$watu_rest = trim($_REQUEST['rest']);
				$watu_p_window_size = trim($_REQUEST['p_window_size']);
				$watu_window_size = trim($_REQUEST['window_size']);	
				$watu_filter_st = trim($_REQUEST['filter_st']);
				$watu_filter_zt = trim($_REQUEST['filter_zt']);
				$watu_filter_keywords = trim($_REQUEST['filter_keywords']);
				$watu_translate = $_REQUEST['translate'];

				update_option('watu_rest',$watu_rest);
				update_option('watu_p_window_size',$watu_p_window_size);
				update_option('watu_window_size',$watu_window_size);
				update_option('watu_filter_st',$watu_filter_st);
				update_option('watu_filter_zt',$watu_filter_zt);
				update_option('watu_filter_keywords',$watu_filter_keywords);
				update_option('watu_translate',$watu_translate);

				echo "参数设置成功！请手动刷新本页。<br />";

			}
	}
	//function watu_common_options_htmlpage end here

	function general_mission_htmlpage(){

		$args=array(
		'orderby' => 'name',
		'order' => 'DESC',
		'hide_empty' => 0
		);

		$categories = get_categories($args);

?>
		<div name="urlsubmit" align="left">
			<form action="" method="post" name="general">
			===============WaTu（挖图）===============<br />
			作者：co2co<br />
			邮箱：co2co@foxmail.com<br />
			Version:<?php echo get_option('watu_version'); ?>内测版<br />
			<br />
			Notices:<br />
			网址要包括“http://”。<br />
			①如果图片链接被设置了些奇怪的字符来阻止第三方程序下载，你可以用这个设置来尝试替换掉特殊字符。<br />
			==============================================================================================<br />
			【常用选项】<br />
			目标网址：<br />x:<input type="text" name="x" value="">y:<input type="text" name="y" value=""><br />
			<textarea name="url" rows="20" cols="60"></textarea><br />
			发表到：
			<select name="category">
				<?php
						foreach ($categories as $category) {
							echo "<option value='".$category->term_id."'>".$category->name."</option>";
						}
				?>
				
			</select><br />
			【附加选项】</br>
			[①图片链接修复]<br />
			bugstr：<input type="text" name="bugstr" value="" />fixstr：<input type="text" name="fixstr" value="" /><br />
			[翻墙代理]<br />
			代理地址：<input type="text" name="proxy" value=""><br />
			<input type="submit" name="提交"><br />
			</form>
		</div>
		==============================================================================================<p />
		<p>
<?php
		require_once('watu_function.php');

		if($_REQUEST['url']!=""){
			//从表单获得采集所需的资料
			$url=$_REQUEST['url'];
			$category=$_REQUEST['category'];
			$bugstr=isset($_REQUEST['bugstr'])?$_REQUEST['bugstr']:NULL;
			$fixstr=isset($_REQUEST['fixstr'])?$_REQUEST['fixstr']:NULL;

			if(isset($_REQUEST['proxy']))
			{
				$proxy=$_REQUEST['proxy'];
			}
			else
			{
				$proxy=NULL;
			}

			if(!empty($_REQUEST['x']) && isset($_REQUEST['y']))
			{
				$x=$_REQUEST['x'];
				$y=$_REQUEST['y'];

				if($x<=0 or $y<0){
					$x=1;
					$y=0; 
				}
			}
			else
			{
				$x=null;
				$y=null;
			}

			$watu= new curl();
			$watu->get_request($url,$bugstr,$fixstr,$proxy,$x,$y,$category);
		}
	}
	//function watu_htmlpage end here

	function schedule_mission_htmlpage(){

		$args=array(
		'orderby' => 'name',
		'order' => 'DESC',
		'hide_empty' => 0
		);

		$categories = get_categories($args);

?>
		<div name="schedule" align="left">
			<form action="" method="post" name="schedule">
			===============WaTu（挖图）===============<br />
			作者：co2co<br />
			邮箱：co2co@foxmail.com<br />
			Version:<?php echo get_option('watu_version'); ?>内测版<br />
			<br />
			Notices:<br />
			网址不要包含"http://"<br />
			==============================================================================================<br />
			循环时长：
			<select name="timing">
				<option value="halfhour">半小时</option>
			</select><br />
			起始页：<input type="text" name="x" value="1" disabled="disabled">步长：<input type="text" name="y" value="0" disabled="disabled"><br />
			发表到：
			<select name="category">
				<?php
						foreach ($categories as $category) {
							echo "<option value='".$category->term_id."'>".$category->name."-".$category->term_id."</option>";
						}
				?>
				
			</select>
			<?php 
				$watu_category=get_option('watu_category');
				if(isset($watu_category)){
					echo "当前定时任务发表到以下ID的分类：".$watu_category;
				} 
			?>
			<br />
			任务目标列表：<br />
			<textarea name="schedule_list" rows="20" cols="40"><?php echo get_option('watu_schedule');  ?></textarea><br />
			<input type="submit" value="Submit">
			</form>
			<p>当前日期：<?php $corrdate=date("Y-M-D h:i:s");echo $corrdate; ?></p>
			<p>最后执行日期：<?php echo get_option('watu_schedule_lasttime'); ?></p>
			<p>距离下次执行计划任务剩余秒数：<?php echo $schedule_time=wp_next_scheduled('setup_post_hook')-time(); ?></p>
		</div>
<?php
		require_once('watu_schedule.php');

		if($_REQUEST['schedule_list']!=""){
			//$timing="hourly"; 锁死，默认1小时检查一次目标网站
			$schedule_list=trim($_REQUEST['schedule_list']);
			//$x=$_REQUEST['x']; 锁死！定时任务查询目标网站的第一页就够了
			//$y=$_REQUEST['y']; 锁死！定时任务查询目标网站的第一页就够了
			$category=$_REQUEST['category'];

			update_option('watu_schedule',$schedule_list);
			update_option('watu_category',$category);

			echo "定时任务添加成功！<br />";
			
		}
	}
	//function submenu_htmlpage end here

	function uninstall_htmlpage(){
	
?>
	<div name="uninstall" align="left">
		<form action="" method="post" name="uninstall">
		===============WaTu（挖图）===============<br />
		作者：co2co<br />
		邮箱：co2co@foxmail.com<br />
		Version:<?php echo get_option('watu_version'); ?>内测版<br />
		<br />
		Notices:<br />
		以下操作并不会删除使用插件期间所发的博文和下载的图片，但会删除爬图的连接记录。<p />
		<h3>警告！执行此操作会导致所有采集记录被删且无法恢复！</h3>
		==============================================================================================<br />
		删除数据库里的挖图记录：<input type="checkbox" name="deldata"><br />
		<input type="submit" value="是的，我要删除数据库里的数据">
		</form>

	</div>

<?php
	
		if(isset($_REQUEST['deldata'])){

			global $wpdb;
			$table_urls=$wpdb->prefix."urls";
			$table_dlink=$wpdb->prefix."dlink";
			$table_localpath=$wpdb->prefix."localpath";

			$drop_table_sql="DROP TABLE $table_urls";
			$wpdb->query($drop_table_sql);
			$drop_table_sql="DROP TABLE $table_dlink";
			$wpdb->query($drop_table_sql);
			$drop_table_sql="DROP TABLE $table_localpath";
			$wpdb->query($drop_table_sql);

			echo "<h3>数据已经删除，插件已无法正常运作。你可以去停用此插件了</h3><br />";
		}
	}


?>