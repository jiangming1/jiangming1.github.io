<?php
/**
 * 
 * Urlrule(URL规则)
 *
 * @package      	YOURPHP
 * @author          liuxun QQ:147613338 <admin@yourphp.cn>
 * @copyright     	Copyright (c) 2008-2011  (http://www.yourphp.cn)
 * @license         http://www.yourphp.cn/license.txt
 * @version        	YourPHP企业网站管理系统 v2.1 2012-10-08 yourphp.cn $
 */
if(!defined("Yourphp")) exit("Access Denied");
class UpgradeAction extends AdminbaseAction {

	protected $filemd5_arr=array();

	private $_filearr = array('Yourphp', 'Public', 'Core', '');
	private $_url = 'http://download.yourphp.cn/';

    function _initialize()
    {	
		parent::_initialize(); 
    } 

	public function index() {

		$v=explode(' ',VERSION);
		$menuid =intval($_REQUEST['menuid']);
		$var=$v[0];
		$upgrade_path = $this->_url.$var.'/patch/';
		$upgrade_path_str = @file_get_contents($upgrade_path);
		$allpatch = json_decode($upgrade_path_str);
		$patchlist = $patchlists= array();

		$key = -1;
		foreach($allpatch as $k=>$v) {
			if(strstr($v, 'patch_'.UPDATETIME)) {
				$key = $k;
				break;
			}
		}
		$key = $key < 0 ? '999' : $key;
		foreach($allpatch as $k=>$v) {
			if($k >= $key) {
				$patchlist[$k]['file'] = $v;
				$time=explode('_',$v);
				$patchlist[$k]['oldtime']=$time[1];
				$time=explode('.',$time[2]);				
				$patchlist[$k]['filemtime']=$time[0];
			}
		}
	
	   

		if(!empty($_GET['do'])) {
			$cover =intval($_REQUEST['cover']);
			
			import("@.ORG.Http"); 
			import("@.ORG.Phpzip"); 

			foreach($patchlist as $k=>$v) {		 

				//远程压缩包地址
				$upgradezip_url =$upgrade_path.$v['file'];
				//保存到临时文件夹
				$upgradezip_file = TEMP_PATH.$v['file'];
				//解压路径
				$upgradezip_source_path = TEMP_PATH.basename($v['file'],".zip");
				//备份路径
				$backupdir = TEMP_PATH.'bakup_'.$v['oldtime'];
				
				dir_create($backupdir);
				//开始下载并解压
				Http::curldownload($upgradezip_url, $upgradezip_file);
				Phpzip::unZip($upgradezip_file,$upgradezip_source_path);
				
				//先做备份
				$backupfilelist=dir_list($upgradezip_source_path);
				foreach((array)$backupfilelist as $k=>$file){
					$fromfile=str_replace($upgradezip_source_path, './', $file);
					$tofile=$backupdir.str_replace($upgradezip_source_path, '', $file);
					if(is_dir($fromfile)){
						mkdir($tofile);
					}elseif(is_file($fromfile)){
						copy($fromfile,$tofile);
					}
				}

				
				$this->copyfileerror=0;
				//复制并加判断是否成功
				$this->copydir($upgradezip_source_path,'./',$cover);

				//如果失败，恢复当前版本
				if($this->copyfileerror){
					$this->copydir($backupdir,'./',$cover);
					die(L('upgrade_error'));
				}else{
					
					if(file_exists($upgradezip_source_path.'/yourphp.sql')){
						$sqldata = file_get_contents($upgradezip_source_path.'/yourphp.sql');
						$sqlFormat = sql_split($sqldata, C('DB_PREFIX'));
						foreach ((array)$sqlFormat as $sql){
								$sql = trim($sql);
								if (strstr($sql, 'CREATE TABLE')){
									preg_match('/CREATE TABLE `([^ ]*)`/', $sql, $matches);
									$ret =$this->excuteQuery($sql);
									//if($ret){echo   L('CREATE_TABLE_OK').$matches[0].' <br />';}else{echo 'Error sql:'.$sql;}exit;
								}else{
									$ret = $this->excuteQuery($sql);
								}
						}
					}
					if(file_exists($upgradezip_source_path.'/upgrade.php')){
						include $upgradezip_source_path.'/upgrade.php';
					}

					dir_delete($upgradezip_source_path);
					@unlink('./upgrade.php');
					@unlink('./yourphp.sql');
					@unlink($upgradezip_file);
				}
			}
			

			$this->assign('jumpUrl',U(MODULE_NAME.'/checkfile?menuid='.$menuid));
			$this->success(L('upgrade_ok'));
			exit;
		}
		$this->assign ( 'menuid',$menuid );
		$this->assign ( 'var',$var );
		$this->assign ( 'patchlist',$patchlist );

		$this->display();
	}
	
	
	//检查文件
	public function checkfile() {

			$this->_readdir(".");
			$v=explode(' ',VERSION);
			$var=$v[0];
			$yourphp_filemd5 = @file_get_contents($this->_url.$var.'/md5file/'.UPDATETIME.".php");
			$yourphp_filemd5_arr = json_decode($yourphp_filemd5, 1);unset($yourphp_filemd5);

			$filemd5_arr_key = array_keys($this->filemd5_arr);
			$yourphp_filemd5_arr_key = array_keys($yourphp_filemd5_arr);

			$editfiles = array_diff($yourphp_filemd5_arr, $this->filemd5_arr);
			$lostfile = array_diff( $yourphp_filemd5_arr_key,$filemd5_arr_key);
			$unknowfile = array_diff($filemd5_arr_key, $yourphp_filemd5_arr_key);
			foreach((array)$lostfile as $file){	unset($editfiles[$file]);	}

			parse_str($_SERVER['QUERY_STRING'],$urlarr);
			unset($urlarr['onlyphp']);
			$onlyphpfileurl='index.php?'.http_build_query($urlarr).'&onlyphp=1';
 
			$onlyphp=intval($_GET['onlyphp']);
			$r=$file_1=$file_2=$file_3=array();
			foreach($editfiles as $key=>$file){
				$r['file']= base64_decode($key);
				$r['ext'] = fileext($r['file']);
				if($onlyphp && $r['ext']!='php') continue;
				$r['filename'] = basename($r['file']);
				$r['filesize']=byte_format(filesize($r['file']));
				$r['filemtime']=filemtime($r['file']);				
				$r['gourl']=str_replace('./', __ROOT__.'/', $r['file']);
				$file_1[] = $r; 
			}
			foreach($lostfile as  $key=>$file){

				$r['file']= base64_decode($file);
				$r['ext'] =  fileext($r['file']);
				if($onlyphp && $r['ext']!='php') continue;
				$r['filename'] = basename($r['file']);
				$r['filesize']=byte_format(filesize($r['file']));
				$r['filemtime']=filemtime($r['file']);
				$r['gourl']=str_replace('./', __ROOT__.'/', $r['file']);
				$file_2[] = $r;
			}

			foreach($unknowfile as $key=>$file){
				$r['file']= base64_decode($file);
				$r['ext'] =  fileext($r['file']);
				if($onlyphp && $r['ext']!='php') continue;
				$r['filename'] = basename($r['file']);
				$r['filesize']=byte_format(filesize($r['file']));
				$r['filemtime']=filemtime($r['file']);
				$r['gourl']=str_replace('./', __ROOT__.'/', $r['file']);
				$file_3[] = $r;
			} 
			$this->assign ( 'onlyphpfileurl',$onlyphpfileurl );
			$this->assign ( 'file_1',$file_1 );
			$this->assign ( 'file_2',$file_2 );
			$this->assign ( 'file_3',$file_3 );
			$this->display();
	  
	}
	public function view() {
		 $file=$_GET['file'];
		 $content=file_get_contents($file);
		 $this->assign ( 'content',$content );
		 $this->display();
	}
	
	public function _readdir($path='') {
		$dir_arr = explode('/', dirname($path));
		if(is_dir($path)) {
			$handler = opendir($path);
			while(($file = @readdir($handler)) !== false) {
				if(substr($file, 0, 1) != ".") {
					$this->_readdir($path.'/'.$file);
				}
			}
			closedir($handler);
		} else {
			if (dirname($path) == '.' || (isset($dir_arr[1]) && in_array($dir_arr[1], $this->_filearr))) {
				$this->filemd5_arr[base64_encode($path)] = md5_file($path);
			}
		}
	}
	
	public function copydir($dirfrom, $dirto, $cover='') {
	    if(is_file($dirto)){
	        die(L('have_file').$dirto);
	    }
	    if(!file_exists($dirto)){
	        mkdir($dirto);
	    }
	    $handle = opendir($dirfrom); 
	    while(false !== ($file = readdir($handle))) {
	    	if($file != '.' && $file != '..'){
		        //源文件名
			    $filefrom = $dirfrom.DIRECTORY_SEPARATOR.$file;
		     	//目标文件名
		        $fileto = $dirto.DIRECTORY_SEPARATOR.$file;
		        if(is_dir($filefrom)){
		            $this->copydir($filefrom, $fileto, $cover);
		        } else {
		        	if(!empty($cover)) {
						if(!copy($filefrom, $fileto)) {
							$this->copyfileerror++;
						    echo L('copy').$filefrom.L('to').$fileto.L('failed')."<br />";
						}
		        	} else {
		        		if(fileext($fileto) == 'html' && file_exists($fileto)) {

						}elseif(!copy($filefrom, $fileto)) {
								$this->copyfileerror++;								
							    echo L('copy').$filefrom.L('to').$fileto.L('failed')."<br />";
		        		}
		        	}
		        }
	    	}
	    }
	}
	public function excuteQuery($sql='')
    {
		if(empty($sql)) return '';
		$db=D('');
		$db =   DB::getInstance();
        $queryType = 'INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|LOAD DATA|SELECT .* INTO|COPY|ALTER|GRANT|TRUNCATE|REVOKE|LOCK|UNLOCK';
        if (preg_match('/^\s*"?(' . $queryType . ')\s+/i', $sql)) {
            $data['result'] = $db->execute($sql);
            $data['type'] = 'execute';
        }else {
				
            $data['result'] = $db->query($sql);
            $data['type'] = 'query';
		
        }
        return $data;
    }
	 
}
?>