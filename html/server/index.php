<?php

	session_start();

    header('Access-Control-Allow-Origin: *');
	header('Content-type: application/json');

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            // may also be using PUT, PATCH, HEAD etc
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

        exit(0);
    }

	if(!file_exists('log')) {
		@mkdir('log', 0775, true);
	}

	$valid_extensions = array('jpg', 'jpeg', 'png', );

	$GLOBALS['python_path'] = '';
	//$GLOBALS['python_path'] = sanitize_path("c:/Python27");

	$GLOBALS['batchs_dir']		= sanitize_path( 'batchs' );
	$GLOBALS['order_dir'] 		= sanitize_path( 'orders' );

	$GLOBALS['tmp_thumbs'] 		= sanitize_path( 'thumbs' );
	$GLOBALS['tmp_works'] 		= sanitize_path( 'works' );
	$GLOBALS['soucesdir']		= sanitize_path( 'config/sources/' );;
	$GLOBALS['tmp_dir'] 		= sanitize_path( 'tmp/tmp'.(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-cli-') );

	$key='batchs_dir'; if(!file_exists($GLOBALS[$key])) { @mkdir($GLOBALS[$key], 0777, true); }
	$key='order_dir'; if(!file_exists($GLOBALS[$key])) { @mkdir($GLOBALS[$key], 0777, true); }
	$key='tmp_dir'; if(!file_exists($GLOBALS[$key])) { @mkdir($GLOBALS[$key], 0777, true); }


	$currentstatefile 	= 'currentstate.json';
	$serverfile 		= 'config/server.json';
	$setupfile 			= 'config/client.json';
	$formatsfile 		= 'config/formats.json';
	$sourcesfile 		= $GLOBALS['soucesdir'].'sources.json';


	if(file_exists($serverfile)) {
		if($s = file_get_contents($serverfile)) {
			$GLOBALS['server'] = json_decode($s);
		}
	}
	if(file_exists($formatsfile)) {
		if($s = file_get_contents($formatsfile)) {
			$GLOBALS['formats'] = json_decode($s);
		}
	}
	if(file_exists($sourcesfile)) {
		if($s = file_get_contents($sourcesfile)) {
			$GLOBALS['sources'] = json_decode($s);
		}
	}
	if(file_exists($setupfile)) {
		if($s = file_get_contents($setupfile)) {
			$GLOBALS['setup'] = json_decode($s);
		}
	}

	if (isset($argc) && $argc > 1 ) {
	    if ( $argv[1] == 'checkFailedJobs' ) {

			$_ds = glob($GLOBALS['batchs_dir'].'*', GLOB_ONLYDIR );
			foreach ($_ds as $d) {
				$_jobs = glob( $d.'/*.job' );
				// if(isset($_REQUEST['debug'])) echo __LINE__.': get '.$d.'/*.job ::: '.var_export($_jobs, true)."\n";
				if(!empty($_jobs)) {
					if(file_exists($d.'/'.'process.bat')) {
								// if(isset($_REQUEST['debug'])) echo __LINE__.':'.time() - filectime($d.'/'.'process.bat')."\n";
						if (time() - filectime($d.'/'.'process.bat')>120) {
							runBatch( basename($d) );
						}
					}
				}
			}
			// remove old archives
			if($GLOBALS['setup']->removeArchiveOlder>13) {
				$now = time();
				foreach ($_ds as $d) {
					if ($now - filemtime($d) >= 60 * 60 * 24 * $GLOBALS['setup']->removeArchiveOlder) { 
						$folder = basename($d);
						if(!empty($folder)) {
							writeLog("removeArchiveOlder\t".$folder."\tdays:".$GLOBALS['setup']->removeArchiveOlder);
							rrmdir($GLOBALS['order_dir'].$folder);
							rrmdir($GLOBALS['batchs_dir'].$folder);
						}
					}
				}
			}

	    }
	    exit(0);
	}


	$url = ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']=='off') ? 'http://' : 'https://').$_SERVER['HTTP_HOST'];
	foreach ($GLOBALS['sources'] as $key => $value) {
		$GLOBALS['sources'][$key]->path = sanitize_path($value->path);
//		$GLOBALS['sources'][$key]['icon'] = $url.$value['icon'];

	}
	//var_export($_REQUEST); exit(0);
	$_id = 'dir';
	if(isset($_REQUEST[$_id])) $_REQUEST[$_id] = decodeFSnational($_REQUEST[$_id]);
	$_id = 'name';
	if(isset($_REQUEST[$_id])) $_REQUEST[$_id] = decodeFSnational($_REQUEST[$_id]);


	$ret = array();
	if(!isset($_REQUEST['q'])) $_REQUEST['q'] ='';



	switch ($_REQUEST['q']) {
		case 'progress':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++


				ob_end_flush();
				ob_implicit_flush();
				$len = 5;
				$_progress_str = str_repeat('0123456789',100)."\n\n";

				header('Accept-Ranges: bytes');
				header('Content-Length: '.$len*strlen($_progress_str) );
				header('Content-Encoding: none', true);
				header('Content-Type: text/plain; charset=UTF-8');
				header('Expires: 0');

				set_time_limit(0);
				for ($i = 0; $i < $len; $i++) {
					sleep(1);	// Тяжелая операция
					echo $_progress_str;
					flush();
				}
				exit;

			break;

		case 'init':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$setup = array('processMode'=>'backend');
			if(file_exists($setupfile)) {
				if($s = file_get_contents($setupfile)) {
					$setup = json_decode($s);
				}
			}

			if(!empty($_REQUEST['reset'])) {
				@rrmdir($GLOBALS['tmp_dir']);
			}

			$data = array();

			if(file_exists($GLOBALS['tmp_dir'].$currentstatefile)) {
				if($s = file_get_contents($GLOBALS['tmp_dir'].$currentstatefile)) {
					$data = json_decode($s);
					if( !checkCurrentState($data) ) {
						$data = array();
					}
				}
			}

			if(empty($data)) {
				$data = array(
				    'appstate' => 'start',
				    'sources' => $GLOBALS['sources'],
				    'currentSource' => null,
				    'formats' => $GLOBALS['formats'],
				    'currentFormat' => null,
				    'images' => array(),
				    'currentImage' => null,
				    'setup' => $setup,
					);
			}
			$ret = array(
				'type'=>'init',
				'error'=> '',
				'data' => $data,
				'setup' => $setup,
				);
			if(empty($_REQUEST['reset'])) sleep(5);
			break;

		case 'configure':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> empty($GLOBALS['server']->password),
					'data'=>'empty password',
					);
			break;


		case 'login':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$success = (!empty($_REQUEST['pass']) && $GLOBALS['server']->password==$_REQUEST['pass']);
			if($success) writeLog('login with PIN');
				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> ($success===false ? 'login failed' : ''),
					'data'=>'',
					);
			break;

		case 'logout':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$GLOBALS['is_admin'] = false;
				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> '',
					'data'=>'',
					);
			break;

		case 'setupFormatSave':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$success = (!empty($_REQUEST['p']) && $GLOBALS['server']->password==$_REQUEST['p']);

			if($success) writeLog("setupFormatSave\t".json_encode($_REQUEST['formats']));


			if($success) {
				if(!empty($_REQUEST['formats'])) {
					$success = file_put_contents($formatsfile, $_REQUEST['formats']);
				}
			}
				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> ($success===false ? 'save failed ' : ''),
					'data'=>'',
					);
			break;

		case 'setupSourcesSave':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$success = (!empty($_REQUEST['p']) && $GLOBALS['server']->password==$_REQUEST['p']);

			if($success) writeLog("setupSourcesSave\t".json_encode($_REQUEST['sources']));

			if($success) {
				if(!empty($_REQUEST['sources'])) {
					$success = file_put_contents($sourcesfile, $_REQUEST['sources']);
				}
			}
				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> ($success===false ? 'save failed ' : ''),
					'data'=>'',
					);
			break;

		case 'setupSourcesListIcons': 
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$success = (!empty($_REQUEST['p']) && $GLOBALS['server']->password==$_REQUEST['p']);

			$_ds = array();
			if($success) {
				$_ds = glob($GLOBALS['soucesdir'].'icons/*.png' );
			}
				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> ($success===false ? 'save failed ' : ''),
					'data'=> $_ds,
					);
			break;

		case 'setupSourcesListScripts': 
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$success = (!empty($_REQUEST['p']) && $GLOBALS['server']->password==$_REQUEST['p']);

			$data = [];
			$_ds = array();
			if($success) {
				$_ds = glob($GLOBALS['soucesdir'].'scripts/*.sh' );
				foreach ($_ds as $k=>$d) {
					$_s = file_get_contents($d);
					$data[] = array(
						'name' => basename($d),
						'script' => $_s,
						);
				}
			}
				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> ($success===false ? 'save failed ' : ''),
					'data'=> $data,
					);
			break;

		case 'loadArchives':
			// ++++++++++++PageSetup load archives list ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$success = (!empty($_REQUEST['p']) && $GLOBALS['server']->password==$_REQUEST['p']);

			$_ds = array();
			if($success) {
				$_ds = glob($GLOBALS['batchs_dir'].'*', GLOB_ONLYDIR );
				foreach ($_ds as $k=>$d) {
					$_ds[$k] = basename($d) ;
				}
			}
			rsort($_ds);

				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> ($success===false ? 'load failed ' : ''),
					'data'=> $_ds,
					);
			break;

		case 'removeArchive':
			// ++++++++++++PageSetup removeArchive++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$success = (!empty($_REQUEST['p']) && $GLOBALS['server']->password==$_REQUEST['p']);

			if($success) writeLog("removeArchive\t".json_encode($_REQUEST['folder']));

			if($success) {
				$folder = sanitize_path($_REQUEST['folder']);
				if(!empty($folder)) {
					rrmdir($GLOBALS['order_dir'].$folder);
					rrmdir($GLOBALS['batchs_dir'].$folder);
				}
			}
				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> ($success===false ? 'delete failed ' : ''),
					'data'=> '',
					);

			break;

		case 'loadArchive':
			// ++++++++++PageSetup load detailed archive++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$success = (!empty($_REQUEST['p']) && $GLOBALS['server']->password==$_REQUEST['p']);

			$archive = array();
			if($success) {
				$folder = sanitize_path($_REQUEST['folder']);
				$_fs = glob($GLOBALS['batchs_dir'].$folder.'*.dat' );
				foreach ($_fs as $datfile) {
					if($s = file_get_contents($datfile)) {
						 $tmp = json_decode($s);
						 $tmp->name = basename($tmp->file);
						 $tmp->file = $GLOBALS['order_dir'].$folder.basename($tmp->file);
						 $archive[] = $tmp;
					}
				}
			}
			sort($archive);
				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> ($success===false ? 'save failed ' : ''),
					'data'=> $archive,
					);
			break;

		case 'saveClientState':
			// ++++++++++++ save current state app++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			if(!empty($_REQUEST['state'])) {
				$success = file_put_contents($GLOBALS['tmp_dir'].$currentstatefile, $_REQUEST['state']);
			}
				$ret = array(
					'type'=>'save',
					'error'=> ($success===false ? 'save failed' : ''),
					'data'=>'',
					);
			break;

		case 'setupPasswordSave':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$success = (!empty($_REQUEST['p']) && $GLOBALS['server']->password==$_REQUEST['p']);

            // first configure
			$success = empty($GLOBALS['server']->password);

			$data = 1;
			if($success) {
			    writeLog("setupPasswordSave");
			    $data = 2;
			}

			if($success && !empty($_REQUEST['value'])) {
				$p['password'] = $_REQUEST['value'];
				$success = @file_put_contents($serverfile, json_encode($p) );
				$data = 3;
			}
				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> ($success===false ? 'save failed' : ''),
					'data'=>$data,
					);
			break;


		case 'setupParametersSave':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$success = (!empty($_REQUEST['p']) && $GLOBALS['server']->password==$_REQUEST['p']);

			if($success) writeLog("setupParametersSave\t".json_encode($_REQUEST['setup']));

			if($success && !empty($_REQUEST['setup'])) {
				$success = @file_put_contents($setupfile, $_REQUEST['setup']);
			}
				$ret = array(
					'type'=>$_REQUEST['q'],
					'error'=> ($success===false ? 'save failed' : ''),
					'data'=>'',
					);
			break;

		// case 'setupLoad':
		// 	// +++++++load PIN+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
		// 	$setup = array('processMode'=>'backend');
		// 	if(file_exists($setupfile)) {
		// 		if($s = file_get_contents($setupfile)) {
		// 			$setup = json_decode($s);
		// 		}
		// 	}
		// 	$ret = array(
		// 		'type'=>$_REQUEST['q'],
		// 		'error'=> '',
		// 		'data' => $setup,
		// 		);
		// 	break;





		case 'src-upload':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$srcuploaddir = 'src-net/';
			$lst = [];

			$success = (!empty($_REQUEST['p']) && $GLOBALS['server']->password==$_REQUEST['p']);

			if($success) writeLog("src-upload");

			if($success) {
				if(!file_exists($srcuploaddir)) @mkdir($srcuploaddir, 0777, true);


				if(isset($_FILES["images"])) {
					foreach ($_FILES["images"]["error"] as $key => $error) {

					    if ($error == UPLOAD_ERR_OK) {
					        $tmp_name = $_FILES["images"]["tmp_name"][$key];
							$name = basename($_FILES["images"]["name"][$key]);
			
							$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION ));
							if(in_array($ext, $valid_extensions)) {
						        
						        move_uploaded_file($tmp_name, $srcuploaddir.$name);
						        $lst[] = $name;
							}
					    }
					}

				} else {

						echo __LINE__.': '.var_export($_FILES, true)."\n";
						echo __LINE__.': '.var_export($_REQUEST, true)."\n";

				}

			}
			$ret = array(
				'type'=>$_REQUEST['q'],
				'error'=> '',
				'data' => implode(';' , $lst),
				);
			break;







		case 'sources':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$ret = array(
				'type'=>'sources',
				'error'=> '',
				'data' => $GLOBALS['sources'],
				);
			break;

		case 'formats':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$ret = array(
				'type'=>'sources',
				'error'=> '',
				'data' => $GLOBALS['formats'],
				);
			break;

		case 'folders':
			// +++++++++PageLoadImages+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$filelist = array();
			$_REQUEST['id'] = intval($_REQUEST['id']);
			if(empty($GLOBALS['sources'][$_REQUEST['id']]->path)) {
				$ret = array(
					'type'=>'list source',
					'id'=>$_REQUEST['id'],
					'error'=> 'empty source',
					'data'=> $filelist,
					);
			} else {
				$dir = '';
				if(isset($_REQUEST['dir']) && !empty($_REQUEST['dir'])) $dir = sanitize_path($_REQUEST['dir']);
				$path = $GLOBALS['sources'][$_REQUEST['id']]->path.$dir;
				if(isset($_REQUEST['debug'])) echo __LINE__.':'.$path."\n";
				if($entries = @scandir($path)) foreach($entries as $entry) {
					if(isset($_REQUEST['debug'])) echo __LINE__.':'.$entry."\n";
					if(is_dir($path.$entry)) {
						if($entry!='.' && $entry!='..') {
							$entry = $entry;
							$filelist[] = array(
								'list'=>$_REQUEST['id'],
								'name'=>encodeFSnational($entry),
								'dir'=>encodeFSnational($dir.$entry),
								);
						}
					}
				}
				

				$_tmp = explode('/', $dir);
				$tree = array();
				foreach ($_tmp as $key => $value) {
					if(!empty($value)) $tree[]=encodeFSnational($value);
				}


				if(isset($_REQUEST['debug'])) echo __LINE__.':'.var_export($tree, true)."\n";

				$ret = array(
					'type'=>'list folders',
					'id'=>$_REQUEST['id'],
					'error'=> '',
					'data'=> $filelist,
					'tree' => $tree,
					);

				if(isset($_REQUEST['debug'])) echo __LINE__.':'.var_export($ret, true)."\n";

			}
			break;

		case 'source':
			// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$progressBar = (isset($_REQUEST['precache']) && $_REQUEST['precache']);
			$filelist = array();
			$_REQUEST['id'] = intval($_REQUEST['id']);
			if(empty($GLOBALS['sources'][$_REQUEST['id']]->path)) {
				$ret = array(
					'type'=>'list source path',
					'id'=>$_REQUEST['id'],
					'error'=> 'empty source',
					'data'=> '$filelist',
					);
			} else {
				$dir = '';
				if(isset($_REQUEST['dir']) && !empty($_REQUEST['dir'])) $dir = sanitize_path($_REQUEST['dir']);
				$path = $GLOBALS['sources'][$_REQUEST['id']]->path.$dir;
				$entries = @scandir($path);
				if($entries) {
					if($progressBar) {
						ob_end_flush();
						ob_implicit_flush();
						$___len = count($entries);
						$___progress_str = str_repeat('0123456789',100)."\n\n";

						header('Accept-Ranges: bytes');
						header('Content-Length: '.($___len*strlen($___progress_str) ));
						header('Content-Encoding: none', true);
						header('Content-Type: text/plain; charset=UTF-8');
						header('Expires: 0');

						set_time_limit(0);
					}
					foreach($entries as $entry) {
						if(!is_dir($path.$entry)) {
							$ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION ));
							if(in_array($ext, $valid_extensions)) {
								$orientation = 0;
								$exif = @exif_read_data($path.$entry);
								if($exif && isset($exif['Orientation'])) {
									$orientation = $exif['Orientation'];
									// 1 - no rotate
									// 3 - 180deg
									// 6 - 90deg
									// 8 - 270deg
								}
								$s = getimagesize ($path.$entry);


								$filelist[] = array(
									'list'=>$_REQUEST['id'],
									'name'=>encodeFSnational($entry),
									'dir'=>encodeFSnational($dir),
									'extension'=>$ext,
									'width'=>$s[0],
									'height'=>$s[1],
									'orientation'=>$orientation,
// TODO: читаю ВСЕ із носія, нахуя? а якщо там тисяча файлів? швидкості не добавляє ніяк
									// 'src'=>makeThumb($_REQUEST['id'], $dir, $entry),
								);
							}
						}
						if($progressBar) {
							echo $___progress_str;
							// flush();
						}
					}
					if($progressBar) {
						exit;
					}

				}

				$ret = array(
					'type'=>'list source',
					'id'=>$_REQUEST['id'],
					'error'=> '',
					'data'=> $filelist,
					);
			}
			break;

		case 'img': 
		// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$_REQUEST['id'] = intval($_REQUEST['id']);
			if(!empty($GLOBALS['sources'][$_REQUEST['id']]->path)) {
				$dir = '';
				if(!empty($_REQUEST['dir'])) $dir = sanitize_path($_REQUEST['dir']);
				if(!isset($_REQUEST['max'])) $_REQUEST['max'] = 0;
				$_REQUEST['max'] = intval($_REQUEST['max']);

				$_REQUEST['src'] = decodeFSnational($_REQUEST['src']);
				$_REQUEST['src'] = sanitize_path($_REQUEST['src'], false);
				$source_file = $GLOBALS['sources'][$_REQUEST['id']]->path.$dir.$_REQUEST['src'];

				$path_dest = $GLOBALS['tmp_dir'].$GLOBALS['tmp_thumbs'].$dir;
				if($_REQUEST['max']<0) { // open full size
					$dest_file = $source_file;
					@unlink($path_dest.$_REQUEST['src']); //remove thumb to recreacte it
				} else { // create thumb or open existed
					$dest_file = makeThumbResampled($source_file, $path_dest, $_REQUEST['max']);
				}

				if(isset($_REQUEST['debug'])) {
					echo "{$dest_file}\n\n";
				} else {
					if(file_exists($dest_file)) {
						$fileinfo = pathinfo($dest_file);
						if(in_array(strtolower( $fileinfo['extension'] ), $valid_extensions)) {
							$type = 'application/octet-stream';
							if($fileinfo['extension']=='jpeg') $type = 'image/jpeg';
							if($fileinfo['extension']=='jpg') $type = 'image/jpeg';
							if($fileinfo['extension']=='png') $type = 'image/png';
							if($fileinfo['extension']=='gif') $type = 'image/gif';
							header('Content-Type:'.$type);
							header('Content-Length: ' . filesize($dest_file));
							readfile($dest_file);
						}
					} else {
						echo "file does not exist : ".$dest_file;
					}

				}
			}
			die();
			break;


		case 'autoclearSource':
		// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$_REQUEST['id'] = intval($_REQUEST['id']);
			if($GLOBALS['sources'][$_REQUEST['id']]->autoclear) {

				$_ds = glob( $GLOBALS['sources'][$_REQUEST['id']]->path.'*.*' );
				foreach ($_ds as $file) {
					@unlink($file);
				}

			}
			break;

		case 'checkEmptySource': 
		// ++++++++++++++++PageStart++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$_REQUEST['id'] = intval($_REQUEST['id']);
			$ret = array(
				'type'=>$_REQUEST['q'],
				'error'=> '',
				'data'=>false,
				);
			if(!empty($GLOBALS['sources'][$_REQUEST['id']]->path)) {
				$path = $GLOBALS['sources'][$_REQUEST['id']]->path.'*';
						if(isset($_REQUEST['debug'])) echo __LINE__.": ".var_export($path, true)."\n";
				$entries = @glob($path);
				if($entries) {
					$ret = array(
						'type'=>$_REQUEST['q'],
						'error'=> '',
						'data'=>true,
						);
				}
				// run scripts AFTER check to avoid on/off lag of source
				if(!empty($GLOBALS['sources'][$_REQUEST['id']]->scripton))
					runScriptsSource($GLOBALS['sources'][$_REQUEST['id']]->scripton);
			} 

			break;

		case 'scriptSource':
		// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$success = runScriptsSource($_REQUEST['scripts']);

			$ret = array(
				'type'=>$_REQUEST['q'],
				'error'=> ($success ? '' : 'error write servicefile'),
				'data'=>'',
				);
			break;

		case 'removetmp': 
		// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			@rrmdir($GLOBALS['tmp_dir']);
			$ret = array(
				'type'=>'removetmp',
				'error'=> '',
				'data'=>'',
				);
			break;

		// case 'thumb':
		// // ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
		// 	$ret = array(
		// 		'type'=>'get_thumb',
		// 		'id'=>$_REQUEST['id'],
		// 		'error'=> '',
		// 		'data'=> makeThumb($_REQUEST['id'], $_REQUEST['dir'], $_REQUEST['name']) ,
		// 	);
		// 	break;

		case 'movetowork':
		// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$progressBar = true;
			// if(isset($_REQUEST['debug'])) $progressBar = false;

			$_REQUEST['id'] = intval($_REQUEST['id']);
			$path_source = $GLOBALS['sources'][$_REQUEST['id']]->path;

			$path_dest = $GLOBALS['tmp_dir'].$GLOBALS['tmp_works'];
			// $path_original = $GLOBALS['tmp_dir'].$GLOBALS['tmp_works'].'original/';
			@rrmdir($path_dest);
			if(!file_exists($path_dest)) @mkdir($path_dest, 0777, true);
			// if(!file_exists($path_original)) @mkdir($path_original);

			$maxWidth = isset($_REQUEST['maxWidth']) ? intval($_REQUEST['maxWidth']) : 0;
			if(!isset($_REQUEST['files'])) $_REQUEST['files'] = array();

			if($progressBar) {
				ob_end_flush();
				ob_implicit_flush();
				$___len = is_array($_REQUEST['files']) ? count($_REQUEST['files']): 0;
				$___progress_str = str_repeat('0123456789',100)."\n\n";

				header('Accept-Ranges: bytes');
				header('Content-Length: '.($___len*strlen($___progress_str) ));
				header('Content-Encoding: none', true);
				header('Content-Type: text/plain; charset=UTF-8');
				header('Expires: 0');

			}

			if(is_array($_REQUEST['files'])) foreach($_REQUEST['files'] as $key=>$file) {
				set_time_limit(180);
				$dir = '';
				if(!empty( $_REQUEST['dirs'][$key] )) $dir = sanitize_path( $_REQUEST['dirs'][$key] );
				$path_thumbs = $GLOBALS['tmp_dir'].$GLOBALS['tmp_thumbs'].$dir;
				$source_file = $path_source.$dir.$file;
				if(!file_exists($path_dest.$file)) {
					$file = decodeFSnational($file);
					$fileinfo = pathinfo($file);

					// if(isset($_REQUEST['debug'])) {
					// 	makeThumbResampled($source_file, $path_thumbs, $maxWidth);
					// } else {
						makeThumbResampled($source_file, $path_thumbs, $maxWidth);
						makeThumbResampled($source_file, $path_dest, 0); // full
					// }


				}
				if($progressBar) {
					echo $___progress_str;
					// flush();
				}

			}

			if($progressBar) {
				exit;
			}

			$ret = array(
				'type'=>$_REQUEST['q'],
				'data'=>$path_dest,
				//'data'=>var_export($_REQUEST['paths'], true),
				'error'=> '',
			);
			break;

		case 'workpath':
		// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$ret = array(
				'type'=>$_REQUEST['q'],
				'data'=>getWorkPath(),
				'error'=> '',
			);
			break;

		case 'orderGetFolder': 
		// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$path_dest = sanitize_path($_REQUEST['folder']);
			$result = true;
			if(empty($path_dest) || !file_exists($GLOBALS['batchs_dir'].$path_dest)) {
				$_ds = glob($GLOBALS['order_dir'].'*', GLOB_ONLYDIR );
				sort($_ds);
				$order = intval(basename($_ds[count($_ds)-1]))+1;
				// $order = date('Y_m_d_H_i_s');
				$path_dest = str_pad($order, 10, "0", STR_PAD_LEFT);
				while(file_exists($GLOBALS['order_dir'].$path_dest) || file_exists($GLOBALS['batchs_dir'].$path_dest)) {
					$order++;
					$path_dest = str_pad($order, 10, "0", STR_PAD_LEFT);
				}
				$result = @mkdir($GLOBALS['batchs_dir'].$path_dest.'/', 0777, true);
				@mkdir($GLOBALS['order_dir'].$path_dest.'/', 0777, true);
				writeLog("src-orderGetFolder\t".$path_dest);
			} else {
				$order = intval($path_dest);
			}

			$ret = array(
				'type'=>$_REQUEST['q'],
				'data'=>$path_dest,
				'orderNum'=>$order,
				'error'=> $result ? '' : 'folder of order create failed',
			);

			break;

		case 'orderFileWrite': 
		// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$folder = sanitize_path($_REQUEST['folder']);
			$path_dest = $GLOBALS['order_dir'].$folder;
			$fileinfo = pathinfo( decodeFSnational($_REQUEST['name']) );
			$count = intval($_REQUEST['count']);
			$copyMode = intval($_REQUEST['copy']);
			$dest_file = $path_dest.str_pad($fileinfo['filename'], 3, "0", STR_PAD_LEFT).formatToImageName($image->format, ($copyMode==1 ? '' : $count)).".".$fileinfo['extension'];
			if($count==0) $count = 1;

			$data = $_POST['data'];

			// remove "data:image/png;base64,"
			$uri =  substr($data,strpos($data,",")+1);

			// save to file
			$_content = base64_decode($uri);
			file_put_contents($dest_file, $_content);
			if($copyMode==1) {
				for($i=1; $i<$count; $i++) {
					$addcopyfile = $fileinfo['filename']."_".($i+1).".".$fileinfo['extension'];
					@copy($dest_file, $path_dest.$addcopyfile);
				}
			}
			$image = json_decode($_REQUEST['image']);
			writeDatFile($folder, $fileinfo, $dest_file, $count, $image->format);

			$ret = array(
				'type'=>$_REQUEST['q'],
				'data'=>encodeFSnational($dest_file),
				'error'=> '',
			);

			break;

		case 'orderFileToProcess': 
		// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$folder = sanitize_path($_REQUEST['folder']);
			$fileinfo = pathinfo( decodeFSnational($_REQUEST['name']) );
			$image = json_decode($_REQUEST['image']);
			$dimensions = json_decode($_REQUEST['dimensions']);
			$count = intval($_REQUEST['count']);
			$copyMode = intval($_REQUEST['copy']);
			$ret = processImage($folder,$fileinfo,$image,$dimensions,$count,$copyMode,isset($_REQUEST['batch']));

			$ret = array(
				'type'=>$_REQUEST['q'],
				'data'=>$ret,
				'error'=> $ret ? '' : 'process '.$_REQUEST['name'].' error',
			);

			break;

		case 'orderFilesProcessBatch': 
		// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			$folder = sanitize_path($_REQUEST['folder']);
			$ret = runBatch($folder);

			$ret = array(
				'type'=>$_REQUEST['q'],
				'data'=>$ret,
				'error'=> $ret ? '' : 'batch start error',
			);

			break;


		default:
			$ret = array(

				'data'=>var_export($_REQUEST, true),
				'error'=> 'no query, look in [data]',
			);
			break;

	}

	

	echo json_encode($ret);
	return;
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================
// ===========================================================================================================

function checkCurrentState($state) {
	$success = true;
	if(!is_object($state)) {
		return false;
	}
	if($state->appstate=='start') {
		return false;
	}

	$success = true;
	$images = $state->images;
	if(is_array($images)) {
		foreach ($images as $image) {
			if(!file_exists($image->src)) {
				$success = false;
			}
		}
	}

	return $success;
}


function sanitize_path($dir, $addslash=true) {
	if(!empty($dir)) {
		$dir = preg_replace("#\\\\#", "/", $dir); 
		$dir = preg_replace("#/$#", "", $dir).($addslash ? '/' : '');
		$dir = preg_replace("#\.\./#", "", $dir); 
	}
	return $dir;
}

function path_rawurlencode($str) {
	return implode('/', array_map('rawurlencode', explode('/', $str)));
}

function encodeFSnational($text) {
	if(stristr(PHP_OS, 'WIN')) {
		$text = iconv('WINDOWS-1251', 'UTF-8', $text);
	}
	return $text;
}
function decodeFSnational($text) {
	if(stristr(PHP_OS, 'WIN')) {
		$text = iconv('UTF-8', 'WINDOWS-1251', $text);
	}
	return $text;
}

function rrmdir($dir) { 
	if (is_dir($dir)) { 
		$objects = scandir($dir); 
		foreach ($objects as $object) { 
			if ($object != "." && $object != "..") { 
				if (is_dir($dir."/".$object))
					rrmdir($dir."/".$object);
				else
					unlink($dir."/".$object); 
			} 
		}
		rmdir($dir);
	} 
}

function runScriptsSource($scripts) {
	$list = [];
	$success = true;

	$servicefile = $GLOBALS['soucesdir'].'service.list';
	if(file_exists($servicefile)) {
		$txt = @file_get_contents($servicefile);
		$exs = explode("\n", $txt);
		foreach ($exs as $s) {
			if(!empty($s)) {
				$list[$s] = $s;
			}
		}
	}

	if(is_array($scripts)) foreach($scripts as $s) {
		if(!empty($s)) {
			$s = sanitize_path($s, false);
			$list[$s] = $s;
		}
	} else { // single value
		if(!empty($scripts)) {
			$s = sanitize_path($scripts, false);
			$list[$s] = $s;
		}
	}
	if(!empty($list)) {
		$success = @file_put_contents($servicefile, implode("\n", $list));
	}
	return $success;
}



 function makeThumb($_id, $_dir, $_name, $destination='') {
	if(!file_exists($GLOBALS['tmp_dir'])) {
		mkdir($GLOBALS['tmp_dir'], 0777, true);
	}
	if(!file_exists($GLOBALS['tmp_dir'].$GLOBALS['tmp_thumbs'])) {
		mkdir($GLOBALS['tmp_dir'].$GLOBALS['tmp_thumbs'], 0777, true);
	}
	$dir = '';
	$_id = intval($_id);
	if(isset($_dir) && !empty($_dir)) $dir = sanitize_path($_dir);
	$path = $GLOBALS['sources'][$_id]->path.$dir;

 	if(empty($destination)) {
 		$destination = $GLOBALS['tmp_dir'].$GLOBALS['tmp_thumbs'].$dir;
 	} else {
		$destination = sanitize_path($destination);
 	}

	$url = '';
	$fileinfo = pathinfo($_name);
	$source = $path.$fileinfo['basename'];    	// Исходный файл 
	if(file_exists($source)) {
	$dest = $destination.$fileinfo['basename'];   // Файл с результатом работы		
		if(!file_exists($dest)) {
			if(!file_exists($destination)) {
				mkdir($destination, 0777, true);
			}
			copy($source , $dest);
		}

		$url = path_rawurlencode(encodeFSnational($dest));

	}

	return $url;	
}


function makeThumbResampled($_name, $destination='', $nw=150, $nh=0) {
						// echo $_name.'---'.$destination . "\n";

	// if(isset($_REQUEST['debug'])) echo __LINE__.':'.$destination."\n";

	//$nw = 150;    // Ширина миниатюр
	//$nh = 0;    // Высота миниатюр
 	if(empty($destination)) {
 		$destination = $GLOBALS['tmp_dir'].$GLOBALS['tmp_thumbs'];
 	} else {
		$destination = sanitize_path($destination);
 	}
	if(!file_exists($GLOBALS['tmp_dir'])) {
		mkdir($GLOBALS['tmp_dir'], 0777, true);
	}
	if(!file_exists($GLOBALS['tmp_dir'].$GLOBALS['tmp_thumbs'])) {
		mkdir($GLOBALS['tmp_dir'].$GLOBALS['tmp_thumbs'], 0777, true);
	}

	$url = '';
	$fileinfo = pathinfo($_name);
	$source = sanitize_path($fileinfo['dirname']).$fileinfo['basename'];    	// Исходный файл 
				// echo "source $source\n";

	if(file_exists($source)) {

		$dest = $destination.$fileinfo['filename'].".jpg";   // Файл с результатом работы		
				// echo "dest $dest\n";
		if(!file_exists($dest)) {

			if(!file_exists($destination)) {
				@mkdir($destination, 0777, true);
			}

			$size = getimagesize($source);
			$w = $size[0];    // Ширина изображения 
			$h = $size[1];    // Высота изображения
			if($nh==0) {
				$nh = $nw / ($w/$h);
			}


			if($nw!=0 && ($w>$nw || $h>$nh) ) {
				switch( strtolower( $fileinfo['extension'] ) ) {
				    case 'gif':
				    $simg = imagecreatefromgif($source);
				    break;
				    case 'jpg':
				    case 'jpeg':
				    $simg = imagecreatefromjpeg($source);
				    break;
				    case 'png':
				    $simg = imagecreatefrompng($source);
				    break;
				}
				$dimg = imagecreatetruecolor($nw, $nh);
				$wm = $w/$nw;
				$hm = $h/$nh;
				$h_height = $nh/2;
				$w_height = $nw/2;
				 
				if($w > $h) {
				    $adjusted_width = $w / $hm;
				    $half_width = $adjusted_width / 2;
				    $int_width = $half_width - $w_height;
				    imagecopyresized($dimg,$simg,-$int_width,0,0,0,$adjusted_width,$nh,$w,$h);
				} elseif(($w < $h) || ($w == $h)) {     
						$adjusted_height = $h / $wm;
						$half_height = $adjusted_height / 2;
						$int_height = $half_height - $h_height;
						imagecopyresized($dimg,$simg,0,-$int_height,0,0,$nw,$adjusted_height,$w,$h);
					 } else {     
						imagecopyresized($dimg,$simg,0,0,0,0,$nw,$nh,$w,$h); 
					 }     
				imagejpeg($dimg,$dest,100);

			} else {
				$dest = $destination.$fileinfo['basename'];
				@copy($source , $dest);
				// echo "copy $dest\n\n\n";
			}
		
		}

		$url = path_rawurlencode(encodeFSnational($dest));

	}

	return $dest;	
}

function dpi_dots2cm($dots, $dpi=false) {
    if(!$dpi) $dpi = 300;
    return $dots / ($dpi / 2.54);
}

function dpi_cm2dots($cm, $dpi=false) {
    if(!$dpi) $dpi = 300;
    return $cm * ($dpi / 2.54);
}

function dpi_dots2inch($dots, $dpi=false) {
    if(!$dpi) $dpi = 300;
    return $dots / $dpi;
}

function dpi_inch2dots($inch, $dpi=false) {
    if(!$dpi) $dpi = 300;
    return $inch * $dpi;
}

function getWorkPath() {
	return array(
		'works' => $GLOBALS['tmp_dir'].$GLOBALS['tmp_works'],
		'thumbs' => $GLOBALS['tmp_dir'].$GLOBALS['tmp_thumbs'],
		);
}

function runBatch($folder) {
	$cmd = $GLOBALS['python_path']."python batchproc.py ".$GLOBALS['batchs_dir']."{$folder} ".$GLOBALS['order_dir']."{$folder}";
	$filecmd = $GLOBALS['batchs_dir'].$folder.'/process.bat';
	file_put_contents($filecmd, "cd ".__DIR__."\n".$cmd);

    if (substr(php_uname(), 0, 7) == "Windows"){ 
        pclose(popen("start /B ". $cmd, "r"));  
    } 
    else { 
		chmod($filecmd, 0755);
		exec($cmd.' >>log/batchout 2>&1 &');
    }
	return $cmd;
}

function formatToImageName($format, $copies) {
	//formatToImageName($image->format)
	// (!empty($format->XXXXX) ? $format->XXXXX : '')
	$inch = (!empty($format->height) ? $format->height : '0') .'x'. (!empty($format->width) ? $format->width : '0');
	$mm = round( (!empty($format->height) ? $format->height : 0)*25.4) .'x'. round((!empty($format->width) ? $format->width : 0)*25.4);
	$sf = (!empty($format->surface) ? $format->surface : '');

	$_mode = (empty($GLOBALS['setup']->printNaming) ? 0 : $GLOBALS['setup']->printNaming);

	switch ($_mode) {
		case '1':
			$printNaming="_{$mm}_{$sf}";
			break;

		case '2':
			$printNaming="_{$inch}_{$sf}";
			break;
		
		case '3':
			$printNaming="_{$mm}";
			break;

		case '4':
			$printNaming="_{$inch}";
			break;
		
		case '5':
			$printNaming="_{$inch}_{$mm}";
			break;

		default:
			$printNaming="_{$inch}_{$mm}_{$sf}";
			break;
	}

	if(!empty($copies)) {
		$_mode = (empty($GLOBALS['setup']->copiesNaming) ? 0 : $GLOBALS['setup']->copiesNaming);
		switch ($_mode) {
			case '1':
				$printNaming="{$printNaming}_copy{$copies}";
				break;

			case '2':
				$printNaming="{$printNaming}_c{$copies}";
				break;
			
			case '3':
				$printNaming="_c{$copies}{$printNaming}";
				break;

			default:
				$printNaming="_copy{$copies}{$printNaming}";
				break;
		}
	}

	return $printNaming;
}

function writeLog($str) {
	$str = date('Y-m-d H:i:s')."\t".$_SERVER['REMOTE_ADDR']."\t".$str."\n";
	@file_put_contents( 'log/altpicture.log', $str , FILE_APPEND ) ;
}

function writeDatFile($folder, $fileinfo, $dstFile, $count, $format) {
	$data = array(
		'file' => $dstFile,
		'count' => $count,
		'format' => $format,
		'date' => date('Y-m-d'),
		'time' => date('H:i:s'),
		);
	$fld = $GLOBALS['batchs_dir'].$folder;
	if(!file_exists($fld)) {
		@mkdir( $fld, 0777, true);
	}
	@file_put_contents( $fld.$fileinfo['filename'].'.dat', json_encode($data) ) ;
}


function processImage($folder,$fileinfo,$image,$d,$count,$copyMode,$batch=false) {

	$source = $image->src;

	if(file_exists($source)) {

	    $canvasWidth = dpi_inch2dots($image->format->width, $image->format->dpi);
	    $canvasHeight = dpi_inch2dots($image->format->height, $image->format->dpi);


		if($batch) {  // --------------------------------------------------------------- BATCH ------------------
			$tmpfolder = $GLOBALS['batchs_dir'].$folder;
			$tmpfilename = $fileinfo['basename'].'.tmp';
			$dstFile = $tmpfolder.str_pad($fileinfo['filename'], 3, "0", STR_PAD_LEFT).formatToImageName($image->format, ($copyMode==1 ? '' : $count)).'.jpg'; // all to jpg
			if(!file_exists($tmpfolder)) {
				@mkdir($tmpfolder, 0775, true);
			}
			$data = array(
			    'srcFile' => $tmpfolder.$tmpfilename,
			    'dstFile' => $dstFile,
			    'Width' => round($canvasWidth),
			    'Height' => round($canvasHeight),
			    'srcX' => round($d->sX),
			    'srcY' => round($d->sY),
			    'srcWidth' => round($d->sW),
			    'srcHeight' =>  round($d->sH),
			    'dstX' => round($d->pX),
			    'dstY' => round($d->pY),
			    'dstWidth' => round($d->pW),
			    'dstHeight' => round($d->pH),
			    'imageRotate' =>  $image->editorState->imageRotate,
			    'brightness' => $image->editorState->brightness,
			    'contrast' => $image->editorState->contrast,
			    'saturate' => $image->editorState->saturate,
			    'colorR' => $image->editorState->colorR,
			    'colorG' => $image->editorState->colorG,
			    'colorB' => $image->editorState->colorB,
			    'sepia' => $image->editorState->sepia,
			    'count' => $copyMode==1 ? $count : 1,
				);
			@copy($source, $tmpfolder.$tmpfilename);
			file_put_contents( $tmpfolder.$fileinfo['filename'].'.job', json_encode($data) ) ;
			writeDatFile($folder, $fileinfo, $dstFile, $count, $image->format);
			return $tmpfolder.$tmpfilename;

		} else {  //  -------------------------------------------------------------------------------- SINGLE ------

			switch( strtolower( $image->extension ) ) {
			    case 'gif':
			    $simg = imagecreatefromgif($source);
			    break;
			    case 'jpg':
			    case 'jpeg':
			    $simg = imagecreatefromjpeg($source);
			    break;
			    case 'png':
			    $simg = imagecreatefrompng($source);
			    break;
			}

			if($image->editorState->imageRotate>0) {
				$simg = imagerotate($simg, $image->editorState->imageRotate, 0);
			}

			$fragment = imagecreatetruecolor($d->pW, $d->pH);
			imagecopyresampled($fragment, $simg, 0, 0, $d->sX, $d->sY, $d->pW, $d->pH, $d->sW, $d->sH);


			$dimg = imagecreatetruecolor($canvasWidth, $canvasHeight);
			imagefill($dimg, 0, 0, imagecolorallocate($dimg, 255, 255, 255) );
			imagecopy( $dimg, $fragment, $d->pX, $d->pY, 0, 0, $d->pW, $d->pH );


			$dest_file = $GLOBALS['order_dir'].$folder.str_pad($fileinfo['filename'], 3, "0", STR_PAD_LEFT).formatToImageName($image->format, ($copyMode==1 ? '' : $count)).'.jpg'; 

			$dest_file_tmp = $dest_file.'.tmp';
			imagejpeg($dimg,$dest_file_tmp,80);

			// // Doing a conversion using ImageMagick
			// // Example CSS3 values of brightness and contrast
			// $b = $image->editorState->brightness;
			// $c = $image->editorState->contrast;
			// // Calculate level values
			// $z1 = ($c - 1) / (2 * $b * $c);
			// $z2 = ($c + 1) / (2 * $b * $c);
			// $cmd = 'C:/wamp/bin/apache/apache2.4.9/bin/convert '.__DIR__.'/'.$dest_file.' -level '.($z1 * 100).'%,'.($z2 * 100).'% '.__DIR__.'/'.$dest_file;
			// exec($cmd);

			// Doing a conversion using Python PIL
			$_fs = $dest_file_tmp;
			$_fd = $_fs;
			//$_fd = $folder.$fileinfo['filename']."_py.".$fileinfo['extension'];
			$cmd = $GLOBALS['python_path']."python filter.py {$_fs} {$_fd}";
			$cmd .= " region ".ceil($d->pX).' '.ceil($d->pY).' '.floor($d->pW).' '.floor($d->pH);
			if($image->editorState->brightness!=1 || $image->editorState->contrast!=1 || $image->editorState->colorR!=1 || $image->editorState->colorG!=1 || $image->editorState->colorB!=1) {
				$cmd .= " lev ".$image->editorState->brightness.' '.$image->editorState->contrast.' '.$image->editorState->colorR.' '.$image->editorState->colorG.' '.$image->editorState->colorB;
			}
			if($image->editorState->sepia) {
				$cmd .= " sep ";
			}
			if($image->editorState->saturate!=1) {
				$cmd .= " sat ".$image->editorState->saturate;
			}

			// $cmd .= " >> ".$folder.$fileinfo['filename'].".log";

			$_timestart = date("H:i:s");
			$runcount = 0;
			$return_value = -1;
			$ALL_OK_EXIT_CODE = 0; // WARN - necessarily set all-ok code in python script
			while ($runcount <= 5 && $return_value!=$ALL_OK_EXIT_CODE) {
				sleep(1);
				set_time_limit(300);
				system($cmd,$return_value);
				$runcount++;
			}
			$renamecount = 0;
			if($return_value==$ALL_OK_EXIT_CODE) {
				$renamed = false;
				while ($renamecount <= 9 && !$renamed) {
					sleep(1);
					$renamed = @rename($_fd, $dest_file);
					$renamecount++;
				}
				for($i=1; $i<$count; $i++) {
					$addcopyfile = $fileinfo['filename']."_".($i+1).".jpg";
					@copy($dest_file, $folder.$addcopyfile);
				}
			}

			// $txt = '';
			// $txt .= "brightness=".$image->editorState->brightness."\r\n";
			// $txt .= "contrast=".$image->editorState->contrast."\r\n";
			// $txt .= "colorR=".$image->editorState->colorR."\r\n";
			// $txt .= "colorG=".$image->editorState->colorG."\r\n";
			// $txt .= "colorB=".$image->editorState->colorB."\r\n";
			// $txt .= "sepia=".var_export($image->editorState->sepia, true)."\r\n";
			// $txt .= "saturate=".$image->editorState->saturate."\r\n";
			// $txt .= json_encode($image->format);
			// $txt .= "\r\n";
			// $txt .= "shell=".$cmd;
			// $txt .= "\r\n";
			// $txt .= "runcount={$runcount}, return_value={$return_value}, renamecount={$renamecount}\r\n";
			// $txt .= "\r\n";
			// $txt .= $_timestart.' - '.date("H:i:s");


			// $filetxt = $fileinfo['filename'].".txt";
			// file_put_contents($folder.$filetxt, $txt);
			writeDatFile($folder, $fileinfo, $dest_file, $count, $image->format);

			return $dest_file;
		}

	} else {
		return false;
	}
}


?>