<?
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org> all rights reserved.
// distributable under GPL see COPYING 
// $Id$

class MoniConfig {
  function MoniConfig($configfile="config.php") {
    if (file_exists($configfile)) {
      $this->config=$this->_getConfig($configfile);
      $this->rawconfig=$this->_rawConfig($configfile);
    } else {
      $this->config=array();
      $this->rawconfig=array();
    }
  }

  function getDefaultConfig() {
    $this->config=$this->_getConfig("config.php.default");

    $hostconfig=$this->_getHostConfig();
    $this->rawconfig=array_merge($this->_rawConfig("config.php.default"),$hostconfig);
    while (list($key,$val)=each($this->rawconfig)) {
      eval("\$$key=$val;");
      eval("\$this->config[\$key]=$val;");
    }

  }
  function _getHostConfig() {
    if (function_exists("dba_open")) {
      $tempnam="/tmp/".time();
      if ($db=@dba_open($tempnam,"n","db3"))
        $config['dba_type']="'db3'";
      else if ($db=@dba_open($tempnam,"n","db2"))
        $config['dba_type']="'db2'";
      else if ($db=@dba_open($tempnam,"n","gdbm"))
        $config['dba_type']="'gdbm'";

      if ($db) dba_close($db);
    }
    preg_match("/Apache\/2\.0\./",$_SERVER['SERVER_SOFTWARE'],$match);

    if ($match) {
      $config['query_prefix']='"?"';
      $config['kbd_script']='$url_prefix."/css/kbd2.js"';
    }

    $url_prefix= preg_replace("/\/([^\/]+)\.php$/","",$_SERVER['SCRIPT_NAME']);
    $config['url_prefix']="'".$url_prefix."'";

    $config['rcs_user']="'".getenv('LOGNAME')."'";
    return $config;
  }

  function setConfig($config) {
    $this->config=$config;
  }

  function setRawConfig($config) {
    $this->rawconfig=$config;
  }

  function _getConfig($configfile) {
    if (!file_exists($configfile))
      return array();

    $org=array();
    $org=get_defined_vars();
    include($configfile);
    $new=get_defined_vars();

    return array_diff($new,$org);
  }

  function _rawConfig($configfile) {
    $lines=file($configfile);
    foreach ($lines as $line) {
      if ($line[0] != '$') continue;

      $d=explode("=",substr($line,1),2);

      if ($d[0]) {
        $val=preg_replace("/\s*;$/","",trim($d[1]));
        $config[$d[0]]=$val;
      }
    }
    return $config;
  }

  function _getFormConfig($config,$mode=0) {
    $conf=array();
    while (list($key,$val) = each($config)) {
      $val=stripslashes($val);
      if (!isset($val)) $val="''";
      if (!$mode) {
        @eval("\$dum=$val;");
        @eval("\$$key=$val;");
        $conf[$key]=$dum;
      } else {
        $conf[$key]=$val;
      }
      #print("$mode:\$$key=$val;<br/>");
    }
    return $conf;
  }

  function _genRawConfig($config) {
    $lines=array("<?php\n","# automatically generated by monisetup\n");
    while (list($key,$val) = each($config)) {
      if ($key=='admin_passwd' or $key=='purge_passwd')
         $val="'".crypt($val,md5(time()))."'";
      $t=@eval("\$$key=$val;");
      if ($t === NULL)
        $lines[]="\$$key=$val;\n";
      else
        print "<font color='red'>ERROR:</font> <tt>\$$key=$val;</tt><br/>";
    }
    $lines[]="?>\n";
    return $lines;
  }
}

function checkConfig($config) {
  umask(011);
  $dir=getcwd();

  if (!file_exists("config.php") && !is_writable(".")) {
     print "<h3><font color='red'>Please change the permission of some directories writable on your server to initialize your Wiki.</font></h3>\n";
     print "<pre class='console'>\n<font color='green'>$</font> chmod <b>777</b> $dir/data/ $dir\n</pre>\n";
     print "If you want a more safe wiki, try to change the permission of directories with <font color='red'>2777(setgid).</font>\n";
     print "<pre class='console'>\n<font color='green'>$</font> chmod <b>2777</b> $dir/data/ $dir\n</pre>\n";
     print "After execute one of above two commands, just <a href='monisetup.php'>reload this monisetup.php</a> would make a new initial config.php with detected parameters for your wiki.\n<br/>";
     print "<h2><a href='monisetup.php'>Reload</a></h2>";
     exit;
  } else if (file_exists("config.php")) {
     print "<h3><font color='green'>WARN: Please execute the following command after you have completed your configuration.</font></h3>\n";
     print "<pre class='console'>\n<font color='green'>$</font> sh secure.sh\n</pre>\n";
  }

  if (file_exists("config.php")) {
    if (!is_writable($config['data_dir'])) {
      if (02000 & fileperms(".")) # check sgid
        $datadir_perm = 0775;
      else
        $datadir_perm = 0777;
      $datadir_perm = decoct($datadir_perm);
      print "<h3><font color=red>FATAL: $config[data_dir] directory is not writable</font></h3>\n";
      print "<h4>Please execute the following command</h4>";
      print "<pre class='console'>\n".
            "<font color='green'>$</font> chmod $datadir_perm $config[data_dir]\n</pre>\n";
      exit;
    }

    $data_sub_dir=array("cache","user","text");
    if (02000 & fileperms($config['data_dir']))
      $DPERM=0775;
    else
      $DPERM=0777;

    foreach($data_sub_dir as $dir) {
       if (!file_exists("$config[data_dir]/$dir")) {
           umask(000);
           mkdir("$config[data_dir]/$dir",$DPERM);
           if ($dir == 'text')
             mkdir($config['data_dir']."/$dir/RCS",$DPERM);
       } else if (!is_writable("$config[data_dir]/$dir")) {
           print "<h4><font color=red>$dir directory is not writable</font></h4>\n";
           print "<pre class='console'>\n".
             "<font color='green'>$</font> chmod a+w $config[$file]\n</pre>\n";
       }
    }

    $writables=array("upload_dir","editlog_name");

    foreach($writables as $file) {
      if (!is_writable($config[$file])) {
        if (file_exists($config[$file])) {
          print "<h3><font color=red>$config[$file] is not writable</font> :( </h3>\n";
          print "<pre class='console'>\n".
              "<font color='green'>$</font> chmod a+w $config[$file]\n</pre>\n";
        } else {
          if (preg_match("/_dir/",$file)) {
            umask(000);
            mkdir($config[$file],$DPERM);
            print "<h3>&nbsp;&nbsp;<font color=blue>$config[$file] is created now</font> :)</h3>\n";
          } else {
            $fp=@fopen($config[$file],"w+");
            if ($fp) {
              chmod($config[$file],0666);
              fclose($fp);
              print "<h4><font color='green'>$config[$file] is created now</font> ;) </h4>\n";
            } else {
              print "<pre class='console'>\n".
              "<font color='green'>$</font> touch $config[$file]\n".
              "<font color='green'>$</font> chmod a+w $config[$file]\n</pre>\n";
            }
          }
        }
        $error=1;
      } else
        print "<h3><font color=blue>$config[$file] is writable</font> :)</h3>\n";
    }
  }
}

function keyToPagename($key) {
#  return preg_replace("/_([a-f0-9]{2})/e","chr(hexdec('\\1'))",$key);
  $pagename=preg_replace("/_([a-f0-9]{2})/","%\\1",$key);
#  $pagename=str_replace("_","%",$key);
#  $pagename=strtr($key,"_","%");
  return rawurldecode($pagename);
}

function pagenameToKey($pagename) {
  return preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);
}

function show_wikiseed($config,$seeddir='wikiseed') {
  $pages= array();
  $handle= opendir($seeddir);
  while ($file = readdir($handle)) {
    if (is_dir($seeddir."/".$file)) continue;
    $pagename = keyToPagename($file);
    $pages[$pagename] = $pagename;
  }
  closedir($handle);
#  sort($pages);
  $idx=1;

  $num=sizeof($pages);

  #
  $SystemPages="FrontPage|RecentChanges|TitleIndex|FindPage|WordIndex".
  "FortuneCookies|Pages$|".
  "SystemPages|TwinPages|WikiName|SystemInfo|UserPreferences|".
  "InterMap|IsbnMap|WikiSandBox|SandBox|UploadFile|UploadedFiles|".
  "InterWiki|SandBox|".
  "BlogChanges|HotDraw|OeKaki";

  $WikiTag='DeleteThisPage';
  #
  $seed_filters= array(
    "HelpPages"=>array('/^Help.*/',1),
    "Category pages"=>array('/^Category.*/',1),
    "Macro pages"=>array('/Macro$/',1),
    "MoniWiki pages"=>array('/MoniWiki.*|Moni/',1),
    "MoinMoin pages"=>array('/MoinMoin.*/',1),
    "Templates"=>array('/Template$/',1),
    "SystemPages"=>array("/($SystemPages)/",1),
    "WikiTags"=>array("/($WikiTag)/",1),
    "Wiki etc."=>array('/Wiki/',1),
    "Misc."=>array('//',0),
  );

  $wrap=1;

  print "<h3>Total $num pages found</h3>\n";
  print "<form method='post' action=''>\n";
  while (list($filter_name,$filter) = each($seed_filters)) {
    print "<h4>$filter_name</h4>\n";
    foreach ($pages as $pagename) {
      if (preg_match($filter[0],$pagename)) {
        print "<input type='checkbox' name='seeds[$idx]' value='$pagename'";
        if ($filter[1])
          print "checked='checked' />$pagename ";
        else
          print " />$pagename ";
        $idx++;
        if ($wrap++ % 4 == 0) print "<br />\n";
        unset($pages[$pagename]);
      }
    }
    $wrap=1;
  }
  print "<input type='hidden' name='action' value='sow_seed' />\n";
  print "<br /><input type='submit' value='sow WikiSeeds'></form>\n";
}

function sow_wikiseed($config,$seeddir='wikiseed',$seeds) {
  umask(000);
  print "<pre class='console'>\n";
  foreach($seeds as $seed) {
    $key=pagenameToKey($seed);
    $cmd="cp $seeddir/$key $config[text_dir]";
    #system(escapeshellcmd($cmd));
    copy("$seeddir/$key", $config['text_dir']."/$key");
    print $cmd."\n";
  }
  print "</pre>\n";
}

print <<<EOF
<html><head><title>Moni Setup</title>
<style type="text/css">
//<!--
body {font-family:Tahoma;}
h1,h2,h3,h4,h5 {
  font-family:Tahoma;
/* background-color:#E07B2A; */
  padding-left:6px;
  border-bottom:1px solid #999;
}
table.wiki {
/* background-color:#E2ECE5;*/
/* border-collapse: collapse; */
  border: 0px outset #E2ECE5;
}

pre.console {
  background-color:#000;
  padding: 1em 0.5em 0.5em 1em;
  color:white;
  width:80%;
}

td.wiki {
  background-color:#E2ECE2;
/* border-collapse: collapse; */
  border: 0px inset #E2ECE5;
}

//-->
</style>
</head>
<body>
EOF;

print "<h2>Moni Wiki setup</h2>\n";

if (file_exists("config.php") && !is_writable("config.php")) {
  print "<h2><font color='red'>'config.php' is not writable !!</font></h2>\n";
  print "Please change 'config.php' permission as 666 first to write settings<br />\n";

  return;
}

$Config=new MoniConfig();

$config=$_POST['config'];
$update=$_POST['update'];
$action=$_GET['action'] or $_POST['action'];
$newpasswd=$_POST['newpasswd'];
$oldpasswd=$_POST['oldpasswd'];

if ($_SERVER['REQUEST_METHOD']=="POST" && $config) {
  $conf=$Config->_getFormConfig($config);
  $rawconfig=$Config->_getFormConfig($config,1);
  $config=$conf;

  if ($Config->config['admin_passwd']) {
    if (crypt($oldpasswd,$Config->config['admin_passwd']) != 
      $Config->config['admin_passwd']) {
        print "<h3><font color='red'>Invalid password error !!!</font></h3>\n";
        print "If you can't remember your admin password, delete password entry in the 'config.php' and restart 'monisetup'<br />\n";
        $invalid=1;
    } else {
        $rawconfig['admin_passwd']=$newpasswd;
    }
  } else {
    if ($newpasswd)
       $rawconfig['admin_passwd']=$newpasswd;
  }

  if ($update) {
    print "<h3>Updated Configutations for this $config[sitename]</h3>\n";
    $lines=$Config->_genRawConfig($rawconfig);
    print "<pre class='console'>\n";
    $rawconf=join("",$lines);
    #
    ob_start();
    highlight_string($rawconf);
    $highlighted= ob_get_contents();
    ob_end_clean();
    #print str_replace("<","&lt;",$rawconf);
    print $highlighted;
    print "</pre>\n";

    if (!$invalid && (is_writable("config.php") || !file_exists("config.php"))) {
      umask(000);
      $fp=fopen("config.php","w");
      fwrite($fp,$rawconf);
      fclose($fp);
      @chmod("config.php",0666);
      print "<h3><font color='blue'>Configurations are saved successfully</font></h3>\n";
      print "<h3><font color='green'>WARN: Please check <a href='monisetup.php'> your saved configurations</a></font></h3>\n";
      print "If all is good, change 'config.php' permission as 644.<br />\n";
    } else {
      if ($invalid) {
        print "<h3><font color='red'>You Can't write this settings to 'config.php'</font></h3>\n";
      }
    }
  } else
    print "<h3>Read current settings for this $config[sitename]</h3>\n";
} else {
  # read settings

  if (!$Config->config) {
    $Config->getDefaultConfig();
    $config=$Config->config;

    checkConfig($config);
    print "<h2>Welcome ! This is your first installation</h2>\n";

    $rawconfig=$Config->rawconfig;
    print "<h3 color='blue'>Default settings are loaded...</h3>\n";

    $lines=$Config->_genRawConfig($rawconfig);
    $rawconf=join("",$lines);
    umask(000);
    $fp=fopen("config.php","w");
    fwrite($fp,$rawconf);
    fclose($fp);
    @chmod("config.php",0666);
    print "<h3><font color='blue'>Initial configurations are saved successfully.</font></h3>\n";
    print "<h3><font color='red'>Goto <a href='monisetup.php'>MoniSetup</a> again to configure details</font></h3>\n";
    exit;
  } else {
    $config=$Config->config;
    checkConfig($config);
    $rawconfig=$Config->rawconfig;
  }
}

if ($_SERVER['REQUEST_METHOD']=="POST") {
  $seeds=$_POST['seeds'];
  $action=$_POST['action'];
  if ($action=='sow_seed' && $seeds) {
    sow_wikiseed($config,'wikiseed',$seeds);
    print "<h2>WikiSeeds are sowed successfully</h2>";
    print "<h2>goto <a href='wiki.php'>$config[sitename]</a></h2>";
    exit;
  } else if ($action=='sow_seed' && !$seeds) {
    print "<h2><font color='red'>No WikiSeeds are selected</font></h2>";
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD']!="POST") {
  if ($action=='seed') {
    show_wikiseed($config,'wikiseed');
    exit;
  }

  print "<h3>Read current settings for this $config[sitename]</h3>\n";
  print"<table class='wiki' align=center border=1 cellpadding=2 cellspacing=2>";
  print "\n";
  while (list($key,$val) = each($config)) {
    if ($key != "admin_passwd" && $key != "purge_passwd")
    print "<tr><td>\$$key</td><td>$val</td></tr>\n";
  }
  print "</table>\n";

  print "<h3>Change your settings</h3>\n";
  if (!$config['admin_passwd'])
  print "<h3><font color='red'>WARN: You have to enter your Admin Password</h3>\n";
  else if (file_exists('config.php') && !file_exists($config[data_dir]."/text/RecentChanges")) {
    print "<h3><font color='red'>WARN: You have no WikiSeed on your $config[sitename]</font></h3>\n";
    print "<h2>If you want to put wikiseeds on your wiki <a href='?action=seed'>Click here</a> now</h2>";
  }
  print "<form method='post' action=''>\n";
  print "<table align=center border=1 cellpadding=2 cellspacing=2>\n";
  while (list($key,$val) = each($rawconfig)) {
    if ($key != "admin_passwd") {
      $val=str_replace('"',"&#34;",$val);
      print "<tr><td>$$key</td>";
      print "<td><input name='config[$key]' value=\"$val\" size='30'></td></tr>\n";
    }
  }

  if (!$config['admin_passwd']) {
    print "<tr><td><b>\$admin_passwd</b></td>";
    print "<td><input type='password' name='newpasswd' size='30'></td></tr>\n";
  } else  {
    print "<tr><td><b>Old password</b></td>";
    print "<td><input type='password' name='oldpasswd' size='30'></td></tr>\n";
    print "<tr><td><b>New password</b></td>";
    print "<td><input type='password' name='newpasswd' size='30'></td></tr>\n";
  }
  print "<tr><td colspan=2>";
  print "<input type='submit' value='preview'> ";
  if (!$config['admin_passwd'])
  print "<input type='submit' name='update' value='update'></td></tr>\n";
  else
  print "<input type='submit' name='update' value='update'></td></tr>\n";
  print "</table></form>\n";

  if (file_exists('config.php') && !file_exists($config[data_dir]."/text/RecentChanges")) {
    print "<h3><font color='red'>WARN: You have no WikiSeed on your $config[sitename]</font></h3>\n";
    print "<h2>If you want to put wikiseeds on your wiki <a href='?action=seed'>Click here</a> now</h2>";
  }

}

?>
