<?php

@header("content-type:text/html;charset=utf8");
set_time_limit(0);
require($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');

class curl{

	/*******config options*********/
	/****************以下设定如非作者本人或阁下真的明白用途否则请勿更改****************/
	public $preg=array();  //创建数组存放正则表达式后续用来匹配
	//public $testmode=TRUE; //是否开启测试模式，只输出最终符合要求的图片网址
	public $url=array(); //用来存放表单输入的URL地址
	public $urlheader=null; //存放http头后续用来检测下载链接是否完整时添加
	public $dirname=null; //存放下载的图片的文件夹名称
	public $bugstr=null; //存放错误的连接字符
	public $fixstr=null; //存放用来替换连接的字符
	public $proxy=null; //存放代理地址和端口
	public $options=array(); //用于统一设置curlopt的参数
	public $window_size=""; //启动下载图片的进程数。需要注意的是同时下载的最大进程数是30个，超过的进程就算启动了也只能排队等候浪费系统资源
	public $p_window_size=""; //启动匹配列表页、主题页的进程数。需要注意的是同时下载的最大进程数是30个，超过的进程就算启动了也只能排队等候浪费系统资源
	public $rest=""; //采集过程中线程与线程之间等待的秒数，作用是为了防止采集太快IP被封。据说大站得休息至少3秒以上？(注意：初始线程也就是window_size、P_window_size是不受这个限制的，如果大站因此屏蔽IP应该把初始线程数降低)
	//////////////////////////////////////////////////////////////////////////////////////
	/*******config options over here*******/

	public function __construct(){

		global $wpdb;
		$this->urls=$wpdb->prefix."urls";
		$this->dlink=$wpdb->prefix."dlink";
		$this->localpath=$wpdb->prefix."localpath";
		$this->rest=get_option('watu_rest');
		$this->window_size=get_option('watu_window_size'); //启动下载图片的进程数。需要注意的是同时下载的最大进程数是30个，超过的进程就算启动了也只能排队等候浪费系统资源
		$this->p_window_size=get_option('watu_p_window_size');
		
		//$this->connectmsql(); //连接数据库

		$this->options=array(
						CURLOPT_REFERER=>"www.baidu.com",
						CURLOPT_USERAGENT=>"Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)", //果然不喜欢爬虫，是怕被当事人发现？
						CURLOPT_TIMEOUT=>90,
						CURLOPT_HEADER=>FALSE,
						CURLOPT_RETURNTRANSFER=>TRUE
						);
		if(isset($this->proxy)){
					$this->options[CURLOPT_PROXY]=$this->proxy;
		}

		//mysql_query('SET NAMES utf8'); 不用mysql查询命令了，改为用wp自带的wpdb类
		/*
		执行SET NAMES utf8。
		其效果等同于同时设定如下：
		SET character_set_client='utf8';
		SET character_set_connection='utf8';
		SET character_set_results='utf8';
		*/
		//alter database imagedl default character set utf8 collate utf8_general_ci; //更改数据库已有编码类型（创建数据库后执行一次此语句则之后创建的表都是此编码类型）
		//alter table dlink default character set utf8 collate utf8_general_ci; //更改表已有编码类型
		//set @@character_set_server='utf8'; //更改mysql编码
		/////////////////////////////////////////////////////////
	}
	//function __construct() over here

	public function choose_preg($url){

		echo "尝试确认BBS程序的版本<br />";
		$res=$this->getweb($url);
		if(preg_match_all('/name=[\"]generator[\"]\s*content=[\"]Discuz\!\sX(.*?)[\"]/', $res, $output)){
			(float)$version=$output[1];
			if($version>=3){
				//当前版:Discuz!X2.5、X3.1论坛适配版
				$this->preg['imgpreg']="/<img.*\s*zoomfile=[\"|\'](.*?)[\"|\']/";
				$this->preg['urlpreg']="/<a\s*href=[\"|\']([a-zA-Z0-9\-\/\:.]*?)[\"|\']\s*onclick=[\"|\']atarget\(this\)[\"|\']\stitle=[\"|\'].*[\"|\']\sclass=[\"|\']z[\"|\']>/";
				$this->preg['titlepreg']="/<title>(.*?)\s.*<\/title>/";
				$this->preg['keywordspreg']="/name=[\"]keywords[\"]\s*content=[\"](.*)[\"]/";
				echo "版本确认成功！BBS程序的版本大于3！<br />";
				//return true;
			}
			elseif($version>=2 and $version<3){
				//当前版:Discuz!X2.0论坛适配版
				$this->preg['imgpreg']="/<img.*\s*file=[\"|\'](.*?)[\"|\']/";
				$this->preg['urlpreg']="/<a\s*href=[\"|\']([a-zA-Z0-9\-\/\:.]*?)[\"|\']\s*onclick=[\"|\']atarget\(this\)[\"|\']\stitle=[\"|\'].*[\"|\']\sclass=[\"|\']z[\"|\']>/";
				echo "版本确认成功！BBS程序的版本小于3但大于2！<br />";
				//return true;
			}
			else{
				die("暂时无法处理discuz x2.0以下的版本……"); 
				//return false;
			}
		}
		else{
			die("网站程序版本确认失败……");
		}
	}
	//function choose_preg() over here

	//过滤函数
	public function safe($input_strings){

		$string = htmlspecialchars($input_strings);
		$string = str_replace("~", "", $string);
		$string = str_replace("-", "", $string);
		$string = str_replace(":", "", $string);
		$string = str_replace("%", "", $string);
		$string = str_replace("$", "", $string);
		$string = str_replace("\\", "", $string);
		$string = str_replace("/", "", $string);
		$string = str_replace("?", "", $string);
		$string = str_replace("<", "", $string);
		$string = str_replace(">", "", $string);
		$string = str_replace("'", "", $string);
		return $string;
	}

	//接受请求并初始化程序的函数，也是本程序的入口
	public function get_request($url,$bugstr,$fixstr,$proxy,$x,$y){

		echo "初始化数据……<br />";
		//检查最关键的curl扩展是否能用，否则怎么努力都是白费功夫
		if(!function_exists(curl_init)){die("<font color=red>Curl模块没有启用！程序无法正常运行！</font>");}
		//接收表单参数
		$this->url=array($url);
		//创建http头后续用来检测下载链接是否完整时添加
		$urlheader_temp=explode("/",$url);
		$this->urlheader="http://".$urlheader_temp[2]; //这是完整的http头部
		$this->domain=$urlheader_temp[2]; //这是目标的域名
		if(isset($x) and isset($y)){
			$this->linkpage($url,$x,$y);
		}
		else
		{
			if(strstr($url, ",")){
				$urls=array_unique(explode(',', $url));
				$i=1;
				foreach ($urls as $key => $value) {
					$dlink[$i]=trim($value);
					$i++;
				}
				unset($i);
				unset($urls_to_array);
				unset($urls_replace);
				unset($urls);
			}
			else{
				$dlink[1]=$url; //为了和之后批量操作的起始指针一致，所以这里定为1开始。指针用1开始是为了方便控制并发数
			}

			$this->choose_preg(reset($dlink)); //调用正则表达式选择函数，确认要用到哪种正则表达式

			$this->subjectpage($dlink);
		}
	}
	//function get_request() over here

	//图片地址匹配函数
	public function subjectpage($dlink){

		global $wpdb;
		
		if($dlink!=null){
			$dlink=$dlink;
		}
		else{
			$geturls=$wpdb->get_results("select url from $this->urls where log='0'");
				$i=1;
				$j=count($geturls);
				if($j==0){echo("所有主题页都处理过了！<br />");}
				foreach ($geturls as $geturl) {
					$dlink[$i]=$geturl->url;
					$i++;
				}
			unset($i);
		}

		$i=1;
		$totalsubjectnum=count($dlink);
		$mh=curl_multi_init();

		while($i<=$this->p_window_size and $i<=$totalsubjectnum){
			$connect[$i]=curl_init();
			$this->options[CURLOPT_URL]=$dlink[$i];
			echo "正在处理此主题：".$dlink[$i]."<br />";
			curl_setopt_array($connect[$i],$this->options);
			curl_multi_add_handle($mh,$connect[$i]);
			$i++;
		}
	
		$active=null;
		do{
			do{
				$code=curl_multi_exec($mh,$active);
					}while($code==CURLM_CALL_MULTI_PERFORM);
			curl_multi_select($mh);
			while($done=curl_multi_info_read($mh)){
				$info=curl_getinfo($done['handle']);
				//标记已经处理过的连接
				$wpdb->query("update $this->urls set log='1' where url='{$info['url']}'");
				
				if($info['http_code']==200){		//如果传输的数据不是对的，那处理也没意义吧			
					$content_temp=curl_multi_getcontent($done['handle']);
					$content=mb_convert_encoding($content_temp,"utf8","gb2312");
					//确保character_set_connection、character_set_results、character_set_client是utf8的话便不用encode（源网页编码是utf8的话），否则就算encode过去也是乱码
					if(preg_match_all($this->preg['imgpreg'],$content,$img_urls_output)) //判断是否有匹配下载链接的图片地址
					{
						foreach($img_urls_output[1] as $j=>$value){ //如果有，判断地址是否为绝对路径，倘若是相对路径则在前面加上header头
							//if(!preg_match_all('/http\:\/\//',$value,$notneedarrayreturn)) { //同上，最后一个数组参数只是用来占位
							if(!strstr($value, "http://")){
								$img_urls_output[1][$j]=$this->urlheader."/".$img_urls_output[1][$j];
							}
						}
						unset($j);
						unset($value);
						//处理错误的URL
						if($this->bugstr!=""){
							echo "<font color=blue>尝试修复畸形连接……</font><p />";
							$img_urls_output[1]=str_replace($this->bugstr,$this->fixstr,$img_urls_output[1]);
						}
						//匹配标题
						if(preg_match_all($this->preg['titlepreg'], $content, $title_output)){
							$title=$this->safe($title_output[1][0]);
						}
						else{
							echo "标题匹配失败！<br />";
						}
						//匹配keywords
						if(preg_match_all($this->preg['keywordspreg'], $content, $keywords_output)){
							$keywords=$this->safe($keywords_output[1][0]);
						}
						else{
							echo "关键词匹配失败！<br />";
						}
						
						foreach($img_urls_output[1] as $k=>$v){  //把最终提取出的图片链接放进数据库
							$imglinks=$v; 
							$checksql=$wpdb->get_results("Select id,dlink,log from $this->dlink where dlink='{$imglinks}'");
							if(count($checksql)==0){
								$wpdb->query("Insert into $this->dlink(dlink,log,domain,title,referer,keywords) values('$imglinks','0','$this->domain','$title','{$info['url']}','{$keywords}')");
							}
							else{
								echo $imglinks.":此图片连接已经存在，不再处理此连接。<br />";
							}
							
						}
						unset($k); //此处原来为i，并且用完没用unset销毁变量，结果导致出现逻辑错误，后面的添加句柄全乱了！教训是常用的变量例如foreach里的i\v\k等等用完最好销毁，要小心！
						unset($v);
					}
					else{
						echo "<font color=red>该主题没有可以提取的图片连接，可能是正则表达式匹配失败或者页面设置了浏览权限！</font><p />";
					}	
				}
				if($i<=$totalsubjectnum){
					$connect[$i]=curl_init();
					$this->options[CURLOPT_URL]=$dlink[$i];
					echo "正在处理此主题：".$dlink[$i]."<br />";  //为什么这里的输出不显示URL呢……
					curl_setopt_array($connect[$i],$this->options);
					sleep($this->rest); //停顿防止被封IP
					curl_multi_add_handle($mh,$connect[$i]);
					$i++;
				}
				curl_multi_remove_handle($mh,$done['handle']);
				curl_close($done['handle']);
			}
		}while($active);
		curl_multi_close($mh);

		echo "主题页处理完毕！<br />";

		//进入图片下载函数
		$this->getimage();
			
	}
	//function subjectpage() over

	//主题地址匹配函数
	public function linkpage($url,$x,$y){

		global $wpdb;

		//通过循环构造一个符合要求的URL数组（从当前页递增Y页）
		$input_url_array=explode('*',(string)$url);
		$z=$x+$y;
		for($i=$x;$i<=$z;$i++)
		{
			$urls_temp[$i]=$input_url_array[0].$i.$input_url_array[1];
		}

		//把指针重新定位到从1开始，以方便控制并发数
		for($j=1;$j<=count($urls_temp);$j++){
			$urls[$j]=$urls_temp[$x];
			$x++;
		}

		$this->choose_preg(reset($urls)); //调用正则表达式选择函数，确认要用到哪种正则表达式(就用URL数组里的第一个链接去确认，反正每个页面都是一样的)

		unset($i);

		$i=1;
		$totalpagenum=count($urls);
		$mh=curl_multi_init();

		while($i<=$this->p_window_size and $i<=$totalpagenum){
			$connect[$i]=curl_init();
			$this->options[CURLOPT_URL]=$urls[$i];
			echo "正在处理此页面：".$urls[$i]."<br />";
			curl_setopt_array($connect[$i],$this->options);
			curl_multi_add_handle($mh,$connect[$i]);
			$i++;
		}
	
		$active=null;
		do{
			do{
				$code=curl_multi_exec($mh,$active);
					}while($code==CURLM_CALL_MULTI_PERFORM);
			curl_multi_select($mh);
			while($done=curl_multi_info_read($mh)){
				$info=curl_getinfo($done['handle']);
				if($info['http_code']==200){		//如果传输的数据不是对的，那处理也没意义吧
					$content=curl_multi_getcontent($done['handle']);
					///////////////////////////////////////////////////////////////
					if(preg_match_all($this->preg['urlpreg'],$content,$url_output)) //是否匹配？是否已经有记录？
					{
							foreach($url_output[1] as $key=>$value)
							{
								//判断地址是否为绝对路径，倘若是相对路径则在前面加上header头
								//if(!preg_match_all('/http\:\/\//',$value,$notneedarrayreturn)) //notneedarrayreturn数组是多余的，仅用于占位免得某些版本的php会敬告缺少参数
								if(!strstr($value, "http://")){
									$dlink=$this->urlheader."/".$value;
								}
								else
								{
									$dlink=$value;
								}

								$checksql=$wpdb->get_results("Select url from $this->urls where url='$dlink'");
								if($wpdb->num_rows==0){
									$wpdb->query("Insert into $this->urls(url,log,domain,referer) values('$dlink','0','$this->domain','{$info['url']}')");
								}
								else{
									echo $dlink."此主题页已经存在于数据库中了，不再进行存储。<br />";
								}
								
							}
					}
					else
					{
						echo "<font color=red>此列表页没有可用的主题页连接，可能是正则表达式匹配失败！</font><p />";
					}
				}
				if($i<=$totalpagenum){
					$connect[$i]=curl_init();
					$this->options[CURLOPT_URL]=$urls[$i];
					echo "正在处理此页面：:".$urls[$i]."<br />";
					curl_setopt_array($connect[$i],$this->options);
					sleep($this->rest); //停顿防止被封IP
					curl_multi_add_handle($mh,$connect[$i]);
					$i++;
				}
				curl_multi_remove_handle($mh,$done['handle']);
				curl_close($done['handle']);
			}
		}while($active);
		curl_multi_close($mh);

		echo "列表页处理完毕！<br />";
		$this->subjectpage($url=null); //调用图片地址挖掘函数取出每一个主题页里面的图片链接
	}
	//function linkpage() over here
	
	//单线程获取数据，适用于获取版本号、翻译单词等零碎资料 
	public function getweb($url){

		$ch=curl_init();
		if(isset($this->options[CURLOPT_FILE])){
			unset($this->options[CURLOPT_FILE]);  //如果之前因为下载图片等原因使用过curl的file参数，会导致数据流入file使返回值为空！这就是之前标题为空的原因！
		}
		$this->options[CURLOPT_URL]=$url;
		curl_setopt_array($ch,$this->options);
		$result=curl_exec($ch);
		if(!$result)
		{
			echo curl_errno($ch).":".curl_error($ch)."<br />";
		}
		else
		{
			curl_close($ch);
			return $result;
		}
	}
	//function getweb() over here

	//图片下载函数
	public function getimage(){	//这个函数负责下载远程web服务器上的图片

		global $wpdb;

		$this->upload_dir = wp_upload_dir();
		$dirname=$this->upload_dir['path'];
		if(!file_exists($dirname))
		{
							
			if(!mkdir($dirname,0777,true))
			{
				die("创建文件夹失败！<br />");
			}
		}
		
		$mh=curl_multi_init();
		$imagedlinks=$wpdb->get_results("Select title,dlink,keywords from $this->dlink where log='0'");

		//如果没有图片需要下载就立即去检查是否需要发博文
		if($wpdb->num_rows==0){

			echo "没有图片需要下载，应该是已经下载过了。即将检查是否有记录需要发表博文……<br />";
			$this->public_to_blog();

		}
		else{

			$i=1;
			$totalnum=count($imagedlinks); 
			foreach($imagedlinks as $imagedlink){
				$map[$i]=array($imagedlink->title,$imagedlink->dlink,$imagedlink->keywords); //循环取出每一个下载连接到map数组
				$i++;
			}
			unset($i);

			$i=1;
			while($i<=$this->window_size and $i<=$totalnum){

				$url_lastname=basename($map[$i][1]);
				$filetype_temp=explode('.',$url_lastname);
				$filetype=end($filetype_temp);
				$lastname=time()."_".rand().".".$filetype;
				$filename=$dirname."/".$lastname;
				$filename=trim($filename);
				$downloadtime=date('y-m-d h:i:s');
				$title=$map[$i][0];
				$keywords=$map[$i][2];

				$wpdb->query("Insert into $this->localpath(title,localpath,downloadtime,log,keywords) values('{$title}','{$filename}','{$downloadtime}','0','{$keywords}')");
				
				
				$connect[$i]=curl_init();
				$this->options[CURLOPT_FILE]=fopen($filename,'w');
				$this->options[CURLOPT_URL]=$map[$i][1];
				curl_setopt_array($connect[$i],$this->options);
				curl_multi_add_handle($mh,$connect[$i]);

				$wpdb->query("Update $this->dlink set log='1' where dlink='{$map[$i][1]}'");
				$i++;
			}

			$active=null;			
			do{
				do{
					$code=curl_multi_exec($mh,$active);
				}while($code==CURLM_CALL_MULTI_PERFORM);
				//if($code!=CURLM_OK){break;}
				curl_multi_select($mh); //当有数据传输时，自动等待
				while($done=curl_multi_info_read($mh)){  //当有线程完成了传输数据的任务，就用curl_multi_info_read把句柄读取出来给done变量
					$info=curl_getinfo($done['handle']);
					if($info['http_code']==200){	//传输若是不正确（非200），那处理来也没意义了吧
						curl_multi_getcontent($done['handle']); //把已经完成任务的线程获取到的数据内容用curl_multi_getcontent读出来
					}
					
					//try to add one handle
					if($i<=$totalnum){
						$url_lastname=basename($map[$i][1]);
						$filetype_temp=explode('.',$url_lastname);
						$filetype=end($filetype_temp);
						$lastname=time()."_".rand().".".$filetype;
						$filename=$dirname."/".$lastname;
						$filename=trim($filename);
						$downloadtime=date('y-m-d h:i:s');
						$title=$map[$i][0];
						$keywords=$map[$i][2];

						$wpdb->query("Insert into $this->localpath(title,localpath,downloadtime,log,keywords) values('{$title}','{$filename}','{$downloadtime}','0','{$keywords}')");

						$connect[$i]=curl_init();
						$this->options[CURLOPT_URL]=$map[$i][1];
						$this->options[CURLOPT_FILE]=fopen($filename,'w');
						curl_setopt_array($connect[$i],$this->options);
						//sleep($this->rest); //下载图片貌似不用sleep……
						curl_multi_add_handle($mh,$connect[$i]);
						
						$wpdb->query("Update $this->dlink set log='1' where dlink='{$map[$i][1]}'");

						$i++;
					}
					//end for try to add one handle
					curl_multi_remove_handle($mh,$done['handle']);  //移除这个已经完成传输任务的线程句柄
					curl_close($done['handle']); //关闭这个句柄
				}
			}while($active); //"一个用来判断操作是否仍在执行的标识的引用"——出自官方说明
			curl_multi_close($mh);
			
			echo "图片已经全部下载完毕，总共下载了".$totalnum."张图片。<p />";
			echo "即将把下载下来的图片发表到博客……<br />";
			$this->public_to_blog();
		}
			
	}
	//function getimage() over here

	//此函数用于将图片发表到blog。
	//如果图片已经下载过，但是对应的blog主题需要删除，那么要手工到数据库把localpath里对应的主题的log记录设置为0，这样下次检测的时候就能再次发表了
	public function public_to_blog(){

		global $wpdb;

		$gettitles=$wpdb->get_results("select title from $this->localpath where log='0' group by title");
		
		if($wpdb->num_rows==0){
			echo "所有记录都已经发表过博文了！<br />";
			die;
		}
		
		foreach ($gettitles as $gettitle) {
		
			$subjectlinks=$wpdb->get_results("select title,localpath,keywords from $this->localpath where title='{$gettitle->title}'");
			
			$deal_title=TRUE; //避免循环的时候要翻译处理每一个标题。毕竟curl拉翻译的数据比if逻辑判断慢多了
			$seemore=TRUE; //是否需要seemore？
			foreach ($subjectlinks as $subjectlink) {

				//处理标题，去掉title的原站数据
				if($deal_title){
					$title_temp=$subjectlink->title;
					if(strstr($title_temp, 95)){
						$title_array=array_unique(explode('_', $title_temp));
						$title=$this->baidufy($title_array[0]);
						$title=$this->safe($title); //过滤一下
					}
					else{
						$title=$this->baidufy($title_temp);
						$title=$this->safe($title); //过滤一下
					}
					$deal_title=FALSE;
				}

				//处理keywords
				$keywords_temp=$this->baidufy($subjectlink->keywords);  //把tag（keywords放在这里拿去给百度翻译速度快很多啊不用foreach）
				$keywords_temp=$this->safe($keywords_temp); //过滤一下
				
				if(strstr($keywords_temp, 44)){
					$keywords_array=array_unique(array_filter(explode(",", $keywords_temp)));
				}
				elseif(strstr($keywords_temp, 124)){
					$keywords_array=array_unique(array_filter(explode("|", $keywords_temp)));
				}
				else{
					$keywords_array=array("Beautiful leg","street shot","pretty girl","stockings"); //同样赋值给予数组，方便下面循环输出，处理手法和lingkpage那里一样啦
				}
				
				$post_name=trim($title); //这是标题栏
				$data=date("Y-m-d H:i:s"); //这是日期


				$imageurl=$this->upload_dir['url']."/".basename($subjectlink->localpath);
				if($seemore===TRUE){
					$content="<img src=\"".$imageurl."\" alt=\"".$title."\" /><p /><p><!--more--></p>";
					$seemore=FALSE;
				}
				else{
					$content.="<img src=\"".$imageurl."\" alt=\"".$title."\" /><p />"; //这是正文
				}
				
				//把属于该主题的图片连接地址都放入一个数组里保存
				$image_array[]=$subjectlink->localpath;
			}

				//print_r($image_array);
				//die;

			$post_data=array(
							'post_status' => 'publish', 
							'post_type' => 'post', 
							'post_author' => 1,
							'ping_status' => get_option( 'default_ping_status' ), 
							'comment_status' => get_option( 'default_comment_status' ),
							'post_pingback' => get_option( 'default_pingback_flag' ),
							'post_parent' => 0,
							'menu_order' => 0, 
							'to_ping' => '', 
							'pinged' => '', 
							'post_password' => '',
							'guid' => '', 
							'post_content_filtered' => '', 
							'post_excerpt' => '', 
							'import_id' => 0,
							'post_content' => $content,  
							'post_title' => $title, 
							'post_category' => '',
							'post_name' => trim($post_name),
							'post_date_gmt' => $data,
							'post_date' => $data, 
							'tags_input' => $keywords_array
							);

			$pid=wp_insert_post($post_data);
			unset($content);

			if($pid){

				//把发表记录设置为真
				
				$wpdb->query("Update $this->localpath set log='1' where title='{$title_temp}'");
				
				echo "博文发表成功！文章ID是：".$pid."<br />";

				//把下载回来的图片全部录入到该文章内成为附件、并且把第一张图设置为特色图片
				$need_thumbnail=true;
				foreach ($image_array as $key => $filename) {
					//$filename=reset($image_array); //节省空间，只用第一张图做附件并且设置为特色图片
					$file_type=wp_check_filetype(basename($filename),null);
					$attachment=array(
										'post_title' => basename($filename),
										'post_mime_type' => $file_type['type']
										);
					$attachment_id=wp_insert_attachment($attachment,$filename,$pid);
					require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-admin/includes/image.php');
					$attachment_data=wp_generate_attachment_metadata($attachment_id,$filename);
					wp_update_attachment_metadata($attachment_id,$attachment_data);
					if($need_thumbnail){
						set_post_thumbnail($pid,$attachment_id);
					}
					$need_thumbnail=false;
				}
					unset($image_array);	
			}
			else{
				echo "博文发表失败！<br />";
				die;
			}
		}
		
		echo "博文已经发表，所有操作已经完成！<br />";
	}
	//function public2blog over here

	//翻译函数，这里用的是百度翻译的API
	public function baidufy($bdfy){

		$from="zh";
		$to="en";
		$apikey="vmLPmdKEaWSwvUZ4WIwnbuKb";
		$bdurl="http://openapi.baidu.com/public/2.0/bmt/translate?client_id=$apikey&q=".trim($bdfy)."&from=$from&to=$to";
		$bdresult_temp=$this->getweb($bdurl);
		$bdresult=json_decode($bdresult_temp,true);
		return $bdresult["trans_result"][0]["dst"];
	}

}
//class over here

?>