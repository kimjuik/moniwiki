<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org> all rights reserved.
// distributable under GPL see COPYING
//
// many codes are imported from the MoinMoin
// some codes are reused from the Phiki
//
// * MoinMoin is a python based wiki clone based on the PikiPiki
//    by Ju"rgen Hermann <jhs at web.de>
// * PikiPiki is a python based wiki clone by MartinPool
// * Phiki is a php based wiki clone based on the MoinMoin
//    by Fred C. Yankowski <fcy at acm.org>
//
// $Id$

function find_needle($body,$needle,$count=0) {
  if (!$body) return '';
  $lines=explode("\n",$body);
  $out="";
  $matches=preg_grep("/($needle)/i",$lines);
  if (count($matches) > $count) $matches=array_slice($matches,0,$count);
  foreach ($matches as $line) {
    $line=preg_replace("/($needle)/i","<strong>\\1</strong>",str_replace("<","&lt;",$line));
    $out.="<br />\n&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$line;
  }
  return $out;
}


class UserDB {
  var $users=array();
  function UserDB($WikiDB) {
    $this->user_dir=$WikiDB->data_dir.'/user';
  }

  function getUserList() {
    if ($this->users) return $this->users;

    $users = array();
    $handle = opendir($this->user_dir);
    while ($file = readdir($handle)) {
      if (is_dir($this->user_dir."/".$file)) continue;
      if (preg_match('/^wu-([^\.]+)$/', $file,$match))
        #$users[$match[1]] = 1;
        $users[] = $match[1];
    }
    closedir($handle);
    $this->users=$users;
    return $users; 
  }

  function getPageSubscribers($pagename) {
    $users=$this->getUserList();
    $subs=array();
    foreach ($users as $id) {
      $usr=$this->getUser($id);
      if ($usr->isSubscribedPage($pagename)) $subs[]=$usr->info[email];
    }
    return $subs;
  }

  function addUser($user) {
    if ($this->_exists($user->id))
      return false;
    $this->saveUser($user);
    return true;
  }

  function saveUser($user) {
    $config=array("css_url","datatime_fmt","email","bookmark","language",
                  "name","password","wikiname_add_spaces","subscribed_pages",
                  "theme");

    $date=date('Y/m/d', time());
    $data="# Data saved $date\n";

    foreach ($config as $key) {
      $data.="$key=".$user->info[$key]."\n";
    }

    #print $data;

    $fp=fopen($this->user_dir."/wu-".$user->id,"w+");
    fwrite($fp,$data);
    fclose($fp);
  }

  function _exists($id) {
    if (file_exists("$this->user_dir/wu-$id"))
      return true;
    return false;
  }

  function getUser($id) {
    if ($this->_exists($id))
       $data=file("$this->user_dir/wu-$id");
    else {
       $user=new User('Anonymous');
       return $user;
    }
    $info=array();
    foreach ($data as $line) {
       #print "$line<br/>";
       if ($line[0]=="#" and $line[0]==" ") continue;
       $p=strpos($line,"=");
       if ($p === false) continue;
       $key=substr($line,0,$p);
       $val=substr($line,$p+1,-1);
       $info[$key]=$val;
    }
    $user=new User($id);
    $user->info=$info;
    return $user;
  }

  function delUser($id) {

  }
}

class User {
  function User($id="") {
     global $HTTP_COOKIE_VARS;
     if ($id) {
        $this->setID($id);
        return;
     }
     $this->setID($HTTP_COOKIE_VARS['MONI_ID']);
     $this->css=$HTTP_COOKIE_VARS['MONI_CSS'];
     $this->theme=$HTTP_COOKIE_VARS['MONI_THEME'];
     $this->bookmark=$HTTP_COOKIE_VARS['MONI_BOOKMARK'];
     $this->trail=stripslashes($HTTP_COOKIE_VARS['MONI_TRAIL']);
  }

  function setID($id) {
     if ($this->checkID($id)) {
        $this->id=$id;
        return true;
     }
     $this->id='Anonymous';
     return false;
  }

  function getID($name) {
     if (strpos($name," ")) {
        $dum=explode(" ",$name);
        $new=array_map("ucfirst",$dum);
        return join($new,"");
     }
     return $name;
  }

  function setCookie() {
     global $HTTP_COOKIE_VARS;
     if ($this->id == "Anonymous") return false;
     setcookie("MONI_ID",$this->id,time()+60*60*24*30,get_scriptname());
     # set the fake cookie
     $HTTP_COOKIE_VARS[MONI_ID]=$this->id;
  }

  function unsetCookie() {
     global $HTTP_COOKIE_VARS;
     header("Set-Cookie: MONI_ID=".$this->id."; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".get_scriptname());
     # set the fake cookie
     $HTTP_COOKIE_VARS[MONI_ID]="Anonymous";
  }

  function setPasswd($passwd,$passwd2="") {
     if (!$passwd2) $passwd2=$passwd;
     $ret=$this->validPasswd($passwd,$passwd2);
     if ($ret > 0)
        $this->info[password]=crypt($passwd);
#     else
#        $this->info[password]="";
     return $ret;
  }

  function checkID($id) {
     $SPECIAL='\\,\.;:\-_#\+\*\?!"\'\?%&\/\(\)\[\]\{\}\=';
     preg_match("/[$SPECIAL]/",$id,$match);
     if (!$id || $match)
        return false;
     return true;
  }

  function checkPasswd($passwd) {
     if (strlen($passwd) < 3)
        return false;
     if (crypt($passwd,$this->info[password]) == $this->info[password])
        return true;
     return false;
  }

  function validPasswd($passwd,$passwd2) {

    if (strlen($passwd)<6)
       return 0;
    if ($passwd2!="" and $passwd!=$passwd2)
       return -1;
    $LOWER='abcdefghijklmnopqrstuvwxyz';
    $UPPER='ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $DIGIT='0123456789';
    $SPECIAL=',.;:-_#+*?!"\'?%&/()[]{}\=~^|$@`';

    $VALID=$LOWER.$UPPER.$DIGIT.$SPECIAL;

    $ok=0;

    for ($i=0;$i<strlen($passwd);$i++) {
       if (strpos($VALID,$passwd[$i]) === false)
          return -2;
       if (strpos($LOWER,$passwd[$i]))
          $ok|=1;
       if (strpos($UPPER,$passwd[$i]))
          $ok|=2;
       if (strpos($DIGIT,$passwd[$i]))
          $ok|=4;
       if (strpos($SPECIAL,$passwd[$i]))
          $ok|=8;
    }
    return $ok;
  }

  function isSubscribedPage($pagename) {
    if (!$this->info[email] or !$this->info[subscribed_pages]) return 0;
    $page_list=explode("\t",$this->info[subscribed_pages]);
    $page_rule=join("|",$page_list);
    if (preg_match('/('.$page_rule.')/',$pagename)) {
      return true;
    }
    return false;
  }
}

function do_download($formatter,$options) {
  global $DBInfo;

  if (!$options[value]) {
    do_uploadedfiles($formatter,$options);
    exit; 
  }
  $key=$DBInfo->pageToKeyname($formatter->page->name);
  if (!$key) {

    exit;
  }
  $dir=$DBInfo->upload_dir."/$key";

  if (file_exists($dir))
    $handle= opendir($dir);
  else {
    $dir=$DBInfo->upload_dir;
    $handle= opendir($dir);
  }
  $file=explode("/",$options[value]);
  $file=$file[count($file)-1];

  if (!file_exists("$dir/$file")) {
    exit;
  }

  $lines = file('/etc/mime.types');
  foreach($lines as $line) {
    rtrim($line);
    if (preg_match('/^\#/', $line))
      continue;
    $elms = preg_split('/\s+/', $line);
    $type = array_shift($elms);
    foreach ($elms as $elm) {
     $mime[$elm] = $type;
    }
  }
  if (preg_match("/\.(.{1,4})$/",$file,$match))
    $mimetype=$mime[$match[1]];
  if (!$mimetype) $mimetype="text/plain";

  header("Content-Type: $mimetype\r\n");
  header("Content-Disposition: attachment; filename=$file" );
  header("Content-Description: MoniWiki PHP Downloader" );
  Header("Pragma: no-cache");
  Header("Expires: 0");

  $fp=readfile("$dir/$file");
  return;
  
 
}

function do_highlight($formatter,$options) {

  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);

  $formatter->highlight=$options[value];
  $formatter->send_page();
  $args[editable]=1;
  $formatter->send_footer($args,$options);
}

function do_post_DeleteFile($formatter,$options) {
  global $DBInfo;

  if ($options[value]) {
    $key=$DBInfo->pageToKeyname($options[value]);
    $dir=$DBInfo->upload_dir."/$key";
  } else {
    $dir=$DBInfo->upload_dir;
  }

  if ($options[files] && $options[passwd]) {
    $check=$DBInfo->admin_passwd==crypt($options[passwd],$DBInfo->admin_passwd);
    if ($check) {
      foreach ($options[files] as $file) {
         $log.=sprintf(_("File '%s' is deleted")."<br />",$file);
         unlink($dir."/".$file);
      }
      $title = sprintf(_("Selected files are deleted !"));
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      print $log;
      $formatter->send_footer();
      return;
    }
    $title = sprintf(_("Invalid password !"));
  } else {
    if (!$options[files])
      $title = sprintf(_("No files are selected !"));
    else
      $title = sprintf(_("Invalid password !"));
  }
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print $log;
  $formatter->send_footer();
  return;
}

function do_DeletePage($formatter,$options) {
  global $DBInfo;
  
  $page = $DBInfo->getPage($options[page]);

  if ($options[passwd]) {
    $check=$DBInfo->admin_passwd==crypt($options[passwd],$DBInfo->admin_passwd);
    if ($check) {
      $DBInfo->deletePage($page);
      $title = sprintf(_("\"%s\" is deleted !"), $page->name);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer();
      return;
    } else {
      $title = sprintf(_("Fail to delete \"%s\" !"), $page->name);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer();
      return;
    }
  }
  $title = sprintf(_("Delete \"%s\" ?"), $page->name);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print "<form method='post'>
Comment: <input name='comment' size='80' value='' /><br />\n";
  print "
Password: <input type='password' name='passwd' size='20' value='' />
Only WikiMaster can delete this page<br />
    <input type='hidden' name='action' value='DeletePage' />
    <input type='submit' value='Delete' />
    </form>";
#  $formatter->send_page();
  $formatter->send_footer();
}

function form_permission($mode) {
  if ($mode & 0400)
     $read="checked='checked'";
  if ($mode & 0200)
     $write="checked='checked'";
  $form= "<tr><th>read</th><th>write</th></tr>\n";
  $form.= "<tr><td><input type='checkbox' name='read' $read /></td>\n";
  $form.= "<td><input type='checkbox' name='write' $write /></td></tr>\n";
  return $form;
}

function do_chmod($formatter,$options) {
  global $DBInfo;
  
  if (isset($options[passwd])) {
    $check=$DBInfo->admin_passwd==crypt($options[passwd],$DBInfo->admin_passwd);
    if ($check && $DBInfo->hasPage($options[page])) {
      $perms= $DBInfo->getPerms($options[page]);
      $perms&= 0077; # clear user perms
      if ($options[read])
         $perms|=0400;
      if ($options[write])
         $perms|=0200;
      $DBInfo->setPerms($options[page],$perms);
      $title = sprintf(_("Permission of \"%s\" changed !"), $options[page]);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    } else {
      $title = sprintf(_("Fail to chmod \"%s\" !"), $options[page]);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    }
  }
  $perms= $DBInfo->getPerms($options[page]);

  $form=form_permission($perms);

  $title = sprintf(_("Change permission of \"%s\""), $options[page]);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
#<tr><td align='right'><input type='checkbox' name='show' checked='checked' />show only </td><td><input type='password' name='passwd'>
  print "<form method='post'>
<table border='0'>
$form
</table>
Password:<input type='password' name='passwd' />
<input type='submit' name='button_chmod' value='change' /><br />
Only WikiMaster can change the permission of this page
<input type=hidden name='action' value='chmod' />
</form>";
#  $formatter->send_page();
  $formatter->send_footer();
}

function do_rename($formatter,$options) {
  global $DBInfo;
  
  if (isset($options[passwd])) {
    $check=$DBInfo->admin_passwd==crypt($options[passwd],$DBInfo->admin_passwd);
    if ($check && $DBInfo->hasPage($options[page]) && !$DBInfo->hasPage($options[value])) {
      $DBInfo->renamePage($options[page],$options[value]);
      $title = sprintf(_("\"%s\" is renamed !"), $options[page]);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    } else {
      $title = sprintf(_("Fail to rename \"%s\" !"), $options[page]);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    }
  }
  $title = sprintf(_("Rename \"%s\" ?"), $options[page]);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
#<tr><td align='right'><input type='checkbox' name='show' checked='checked' />show only </td><td><input type='password' name='passwd'>
  print "<form method='post'>
<table border='0'>
<tr><td align='right'>Old name: </td><td><b>$options[page]</b></td></tr>
<tr><td align='right'>New name: </td><td><input name='value' /></td></tr>
<tr><td align='right'>Password: </td><td><input type='password' name='passwd' />
<input type='submit' name='button_rename' value='rename' />
Only WikiMaster can rename this page</td></tr>
</table>
    <input type=hidden name='action' value='rename' />
    </form>";
#  $formatter->send_page();
  $formatter->send_footer("",$options);
}

function do_RcsPurge($formatter,$options) {
  global $DBInfo;
  
  if ($DBInfo->purge_passwd && $options[passwd]) {
    $check=$DBInfo->purge_passwd==crypt($options[passwd],$DBInfo->purge_passwd);
    if (!$check) {
      $title= sprintf(_("Invalid password to purge \"%s\" !"), $options[page]);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    }
  } else if ($DBInfo->purge_passwd) {
    $title= sprintf(_("You need to password to purge \"%s\""),$options[page]);
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $args[noaction]=1;
    $formatter->send_footer($args,$options);
    return;
  }
#    unset($options);
#    $options[url]=$formatter->link_url($formatter->page->name,"?action=info");
#    do_goto($formatter,$options);
#    return;
#  }
  $title= sprintf(_("RCS purge \"%s\""),$options[page]);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  if ($options[range]) {
    foreach ($options[range] as $range) {
       printf("<h3>range '%s' purged</h3>",$range);
       if ($options[show])
         print "<tt>rcs -o$range ".$options[page]."</tt><br />";
       else {
         #print "<b>Not enabled now</b> <tt>rcs -o$range  data_dir/".$options[page]."</tt><br />";
         print "<tt>rcs -o$range ".$options[page]."</tt><br />";
         system("rcs -o$range ".$formatter->page->filename);
       }
    }
  } else {
    printf("<h3>No version selected to purge '%s'</h3>",$options[page]);
  }
  $args[noaction]=1;
  $formatter->send_footer($args,$options);
}

function do_fullsearch($formatter,$options) {

  $ret=$options;

  if ($options[backlinks])
    $title= sprintf(_("BackLinks search for \"%s\""), $options[value]);
  else
    $title= sprintf(_("Full text search for \"%s\""), $options[value]);
  $out= macro_FullSearch($formatter,$options[value],&$ret);
  $options[msg]=$ret[msg];
  $formatter->send_header("",$options);
  $formatter->send_title($title,$formatter->link_url("FindPage"),$options);

  print $out;

  if ($options[value])
    printf(_("Found %s matching %s out of %s total pages")."<br />",
	 $ret[hits],
	($ret[hits] == 1) ? 'page' : 'pages',
	 $ret[all]);
  $args[noaction]=1;
  $formatter->send_footer($args,$options);
}

function do_goto($formatter,$options) {
  global $DBInfo;
  if (preg_match("/^(http:\/\/|ftp:\/\/)/",$options[value])) {
     $options[url]=$options[value];
     unset($options[value]);
  } else if (preg_match("/^(".$DBInfo->interwikirule."):(.*)/",$options[value],$match)) {
    $url=$DBInfo->interwiki[$match[1]];
    if ($url) {
      $page=trim($match[2]);

      if (strpos($url,'$PAGE') === false)
        $url.=$page;
      else
        $url=str_replace('$PAGE',$page,$url);
      $options[url]=$url;
      unset($options[value]);
    }
  }
  if ($options[value]) {
     $url=stripslashes($options[value]);
     $url=_rawurlencode($url);
     $url=$formatter->link_url($url,"?action=show");
     $formatter->send_header(array("Status: 302","Location: ".$url),$options);
  } else if ($options[url]) {
     $url=str_replace("&amp;","&",$options[url]);
     $formatter->send_header(array("Status: 302","Location: ".$url),$options);
  } else {
     $title = _("Use more specific text");
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     $args[noaction]=1;
     $formatter->send_footer($args,$options);
  }
}

function do_LikePages($formatter,$options) {

  $opts[metawiki]=$options[metawiki];
  $out= macro_LikePages($formatter,$options[page],&$opts);
  
  $title = $opts[msg];
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print $opts[extra];
  print $out;
  print $opts[extra];
  $formatter->send_footer("",$options);
}

function do_rss_rc($formatter,$options) {
  global $DBInfo;

  $lines= $DBInfo->editlog_raw_lines(2000,1);
    
  $time_current= time();
  $secs_per_day= 60*60*24;
  $days_to_show= 30;
  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

  $URL=qualifiedURL($formatter->prefix);
  $img_url=qualifiedURL($DBInfo->logo_img);

  $head=<<<HEAD
<?xml version="1.0" encoding="euc-kr"?>
<!--<?xml-stylesheet type="text/xsl" href="/wiki/css/rss.xsl"?>-->
<rdf:RDF xmlns:wiki="http://purl.org/rss/1.0/modules/wiki/"
         xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:xlink="http://www.w3.org/1999/xlink"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         xmlns="http://purl.org/rss/1.0/">\n
HEAD;
  $url=qualifiedUrl($formatter->link_url("RecentChanges"));
  $channel=<<<CHANNEL
<channel rdf:about="$URL">
  <title>$DBInfo->sitename</title>
  <link>$url</link>
  <description>
    RecentChanges at $DBInfo->sitename
  </description>
  <image rdf:resource="$img_url"/>
  <items>
  <rdf:Seq>
CHANNEL;
  $items="";

#          print('<description>'."[$data] :".$chg["action"]." ".$chg["pageName"].$comment.'</description>'."\n");
#          print('</rdf:li>'."\n");
#        }

  $ratchet_day= FALSE;
  if (!$lines) $lines=array();
  foreach ($lines as $line) {
    $parts= explode("\t", $line);
    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $act= rtrim($parts[6]);

    if ($ed_time < $time_cutoff)
      break;

    if (!$DBInfo->hasPage($page_name))
      $status='deleted';
    else
      $status='updated';
    $zone = date("O");
    $zone = $zone[0].$zone[1].$zone[2].":".$zone[3].$zone[4];
    $date = gmdate("Y-m-d\TH:i:s",$ed_time).$zone;

    $url=qualifiedUrl($formatter->link_url($page_name));
    $channel.="    <rdf:li rdf:resource=\"$url\"/>\n";

    $items.="     <item rdf:about=\"$url\">\n";
    $items.="     <title>$page_name</title>\n";
    $items.="     <link>$url</link>\n";
    $items.="     <dc:date>$date</dc:date>\n";
    $items.="     <dc:contributor>\n<rdf:Description>\n"
          ."<rdf:value>$user</rdf:value>\n"
          ."</rdf:Description>\n</dc:contributor>\n";
    $items.="     <wiki:status>$status</wiki:status>\n";
    $items.="     </item>\n";

#    $out.= "&nbsp;&nbsp;".$formatter->link_tag("$page_name");
#    if (! empty($DBInfo->changed_time_fmt))
#       $out.= date($DBInfo->changed_time_fmt, $ed_time);
#
#    if ($DBInfo->show_hosts) {
#      $out.= ' . . . . '; # traditional style
#      #$logs[$page_name].= '&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ';
#      if ($user)
#        $out.= $user;
#      else
#        $out.= $addr;
#    }
  }
  $url=qualifiedUrl($formatter->link_url($DBInfo->frontpage));
  $channel.= <<<FOOT
    </rdf:Seq>
  </items>
</channel>
<image rdf:about="$img_url">
<title>$DBInfo->sitename</title>
<link>$url</link>
<url>$img_url</url>
</image>
FOOT;

  $url=qualifiedUrl($formatter->link_url("FindPage"));
  $form=<<<FORM
<textinput>
<title>Search</title>
<link>$url</link>
<name>goto</name>
</textinput>
FORM;
  header("Content-Type: text/xml");
  print $head;
  print $channel;
  print $items;
  print $form;
  print "</rdf:RDF>";
}

function do_titleindex($formatter,$options) {
  global $DBInfo;

  $pages = $DBInfo->getPageLists();

  sort($pages);
  header("Content-Type: text/plain");
  print join("\n",$pages);
}

function do_titlesearch($formatter,$options) {

  $out= macro_TitleSearch($formatter,$options[value],&$ret);

  $formatter->send_header("",$options);
  $formatter->send_title($ret[msg],$formatter->link_url("FindPage"),$options);
  print $out;

  if ($options[value])
    printf("Found %s matching %s out of %s total pages"."<br />",
	 $ret[hits],
	($ret[hits] == 1) ? 'page' : 'pages',
	 $ret[all]);
  $args[noaction]=1;
  $formatter->send_footer($args,$options);
}

function do_subscribe($formatter,$options) {
  global $DBInfo;

  if ($options[id] != 'Anonymous') {
    $udb=new UserDB($DBInfo);
    $userinfo=$udb->getUser($options[id]);
    $email=$userinfo->info[email];
    #$subs=$udb->getPageSubscribers($options[page]);
    if (!$email) $title = _("Please enter your email address first.");
  } else {
    $title = _("Please login or make your ID.");
  }

  if ($options[id] == 'Anonymous' or !$email) {
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_page("Goto UserPreferences\n");
    $formatter->send_footer();

    return;
  }

  if ($options[subscribed_pages]) {
    $pages=preg_replace("/\n\s*/","\n",$options[subscribed_pages]);
    $pages=preg_replace("/\s*\n/","\n",$pages);
    $pages=explode("\n",$pages);
    $pages=array_unique ($pages);
    $page_list=join("\t",$pages);
    $userinfo->info[subscribed_pages]=$page_list;
    $udb->saveUser($userinfo);

    $title = _("Subscribe lists updated.");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_page("Goto [$options[page]]\n");
    $formatter->send_footer();
    return;

  }

  $pages=explode("\t",$userinfo->info[subscribed_pages]);
  if (!in_array($options[page],$pages)) $pages[]=$options[page];
  $page_lists=join("\n",$pages);

  $title = sprintf(_("Do you want to subscribe \"%s\" ?"), $options[page]);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print "<form method='post'>
<table border='0'><tr>
<th>Subscribe pages:</th><td><textarea name='subscribed_pages' cols='30' rows='5' value='' />$page_lists</textarea></td></tr>
<tr><td></td><td>
    <input type='hidden' name='action' value='subscribe' />
    <input type='submit' value='Subscribe' />
</td></tr>
</table>
    </form>";
#  $formatter->send_page();
  $formatter->send_footer("",$options);

}

function wiki_notify($formatter,$options) {
  global $DBInfo;

  $from= $options[id];
#  if ($options[id] != 'Anonymous')
#

  $udb=new UserDB($DBInfo);
  $subs=$udb->getPageSubscribers($options[page]);
  if (!$subs) {
    if ($options[noaction]) return 0;

    $title=_("Nobody subscribed to this page, no mail sented.");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    print "Fail !";
    $formatter->send_footer("",$options);
    return;
  }

  $diff="";
  $option="-r".$formatter->page->get_rev();
  $fp=popen("rcsdiff -u $option ".$formatter->page->filename,"r");
  if (!$fp)
    $diff="";
  else {
    while (!feof($fp)) {
      $line=fgets($fp,1024);
      $diff.= $line;
    }
    pclose($fp);
  }

  $mailto=join(", ",$subs);
  $subject="[".$DBInfo->sitename."] ".sprintf(_("%s page is modified"),$options[page]);
  
  $mailheaders = "Return-Path: $from\r\n";
  $mailheaders.= "From: $from\r\n";
  $mailheaders.= "X-Mailer: MoniWiki form-mail interface\r\n";

  $mailheaders.= "MIME-Version: 1.0\r\n";
  $mailheaders.= "Content-Type: text/plain; charset=$DBInfo->charset\r\n";
  $mailheaders.= "Content-Transfer-Encoding: 8bit\r\n\r\n";

  $body=sprintf(_("You have subscribed to this wiki page on \"%s\" for change notification.\n\n"),$DBInfo->sitename);
  $body.="-------- $options[page] ---------\n";
  
  $body.=$formatter->page->get_raw_body();
  if (!$options[nodiff]) {
    $body.="================================\n";
    $body.=$diff;
  }

  mail($mailto,$subject,$body,$mailheaders);

  if ($options[noaction]) return 1;

  $title=_("Send mail notification to all subscribers");
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $msg= str_replace("@"," at ",$mailto);
  print "<h2>".sprintf(_("Mail sented successfully"))."</h2>";
  printf(sprintf(_("mail sented to '%s'"),$msg));
  $formatter->send_footer("",$options);
  return;
}

function do_uploadfile($formatter,$options) {
  global $DBInfo;
  global $HTTP_POST_FILES;

  # replace space and ':'
  $upfilename=str_replace(" ","_",$HTTP_POST_FILES['upfile']['name']);
  $upfilename=str_replace(":","_",$upfilename);

  preg_match("/(.*)\.([a-z0-9]{1,4})$/i",$upfilename,$fname);

  if (!$upfilename) {
     #$title="No file selected";
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     print macro_UploadFile($formatter);
     $formatter->send_footer("",$options);
     return;
  }
  # upload file protection
  if ($DBInfo->pds_allowed)
     $pds_exts=$DBInfo->pds_allowed;
  else
     $pds_exts="png|jpg|jpeg|gif|mp3|zip|tgz|gz|txt|css|exe|hwp";
  if (!preg_match("/(".$pds_exts.")$/i",$fname[2])) {
     $title="$fname[2] extension does not allowed to upload";
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     $formatter->send_footer("",$options);
     return;
  }
  $key=$DBInfo->pageToKeyname($formatter->page->name);
  if ($key != 'UploadFile')
    $dir= $DBInfo->upload_dir."/$key";
  else
    $dir= $DBInfo->upload_dir;
  if (!file_exists($dir)) {
    umask(000);
    mkdir($dir,0777);
    umask(02);
  }

  $file_path= $dir."/".$upfilename;

  # is file already exists ?
  $dummy=0;
  while (!$options[replace] && file_exists($file_path)) {
     $dummy=$dummy+1;
     $ufname=$fname[1]."_".$dummy; // rename file
     $upfilename=$ufname.".$fname[2]";
     $file_path= $dir."/".$upfilename;
  }
 
  $temp=explode("/",$HTTP_POST_FILES['upfile']['tmp_name']);
  $upfile="/tmp/".$temp[count($temp)-1];
  // Tip at http://phpschool.com

  $test=@copy($upfile, $file_path);
  if (!$test) {
     $title=sprintf(_("Fail to copy \"%s\" to \"%s\""),$upfilename,$file_path);
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     return;
  }
  chmod($file_path,0644);

  $REMOTE_ADDR=$_SERVER[REMOTE_ADDR];
  $comment="File '$upfilename' uploaded";
  $DBInfo->addLogEntry($key, $REMOTE_ADDR,$comment,"UPLOAD");
  
  $title=sprintf(_("File \"%s\" is uploaded successfully"),$upfilename);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print "<ins>Uploads:$upfilename</ins>";
  $formatter->send_footer();
}

function do_post_css($formatter,$options) {
  global $DBInfo;
  global $HTTP_COOKIE_VARS;

  if ($options[clear]) {
    if ($options[id]=='Anonymous') {
      header("Set-Cookie: MONI_CSS=dummy; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".get_scriptname());
      $options[css_url]="";
    } else {
      # save profile
      $udb=new UserDB($DBInfo);
      $userinfo=$udb->getUser($options[id]);
      $userinfo->info[css_url]="";
      $udb->saveUser($userinfo);
    }
  } else if ($options[id]=="Anonymous" && isset($options[user_css])) {
     setcookie("MONI_CSS",$options[user_css],time()+60*60*24*30,get_scriptname());
     # set the fake cookie
     $HTTP_COOKIE_VARS[MONI_CSS]=$options[user_css];
     $title="CSS Changed";
     $options[css_url]=$options[user_css];
  } else if ($options[id] != "Anonymous" && isset($options[user_css])) {
    # save profile
    $udb=new UserDB($DBInfo);
    $userinfo=$udb->getUser($options[id]);
    $userinfo->info[css_url]=$options[user_css];
    $udb->saveUser($userinfo);
    $options[css_url]=$options[user_css];
  }
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $formatter->send_page("Back to UserPreferences");
  $formatter->send_footer("",$options);
}

function do_new($formatter,$options) {
  $title=_("Create a new page");
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $url=$formatter->link_url($formatter->page->name);

  $msg=_("Enter a page name");
  print <<<FORM
<form method='get' action='$url'>
    $msg: <input type='hidden' name='action' value='goto' />
    <input name='value' size='30' />
    <input type='submit' value='Create' />
    </form>
FORM;

  $formatter->send_footer();
}

function do_bookmark($formatter,$options) {
  global $DBInfo;
  global $HTTP_COOKIE_VARS;

  $user=new User(); # get cookie
  if (!$options[time]) {
     $bookmark=time();
  } else {
     $bookmark=$options[time];
  }
  if (0 === strcmp($bookmark , (int)$bookmark)) {
    if ($user->id == "Anonymous") {
      setcookie("MONI_BOOKMARK",$bookmark,time()+60*60*24*30,get_scriptname());
      # set the fake cookie
      $HTTP_COOKIE_VARS[MONI_BOOKMARK]=$bookmark;
      $options[msg] = 'Bookmark Changed';
    } else {
      $udb=new UserDB($DBInfo);
      $userinfo=$udb->getUser($user->id);
      $userinfo->info[bookmark]=$bookmark;
      $udb->saveUser($userinfo);
      $options[msg] = 'Bookmark Changed';
    }
  } else
    $options[msg]="Invalid bookmark!";
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $formatter->send_page();
  $formatter->send_footer("",$options);
}

function do_print($formatter,$options) {
  $formatter->send_header();
  $formatter->send_page();
}

function do_userform($formatter,$options) {
  global $DBInfo;

  $user=new User(); # get cookie
  $id=$options[login_id];

  if ($user->id == "Anonymous" and $id and $options[login_passwd]) {
    # login
    $userdb=new UserDB($DBInfo);
    if ($userdb->_exists($id)) {
       $user=$userdb->getUser($id);
       if ($user->checkPasswd($options[login_passwd])=== true) {
          $title = sprintf(_("Successfully login as '%s'"),$id);
          $user->setCookie();
       } else {
          $title = sprintf(_("Invalid password !"));
       }
    } else
       $title= _("Please enter a valid user ID !");
  } else if ($options[logout]) {
    # logout
    $user->unsetCookie();
    $title= _("Cookie deleted !");
  } else if ($user->id=="Anonymous" and $options[username] and $options[password] and $options[passwordagain]) {
    # create profile

    $id=$user->getID($options[username]);
    $user->setID($id);

    if ($user->id != "Anonymous") {
       $ret=$user->setPasswd($options[password],$options[passwordagain]);
       if ($ret <= 0) {
           if ($ret==0) $title= _("too short password!");
           else if ($ret==-1) $title= _("mismatch password!");
           else if ($ret==-2) $title= _("not acceptable character found in the password!");
       } else {
           if ($ret < 8)
              $options[msg]=_("Password is too simple to use as a password !");
           $udb=new UserDB($DBInfo);
           $ret=$udb->addUser($user);
           if ($ret) {
              $title= _("Successfully added!");
              $user->setCookie();
           } else {# already exist user
              $user=$udb->getUser($user->id);
              if ($user->checkPasswd($options[password])=== true) {
                  $title = sprintf(_("Successfully login as '%s'"),$id);
                  $user->setCookie();
              } else {
                  $title = _("Invalid password !");
              }
           }
       }
    } else
       $title= _("Invalid username !");
  } else if ($user->id != "Anonymous") {
    # save profile
    $udb=new UserDB($DBInfo);
    $userinfo=$udb->getUser($user->id);

    if ($options[password] and $options[passwordagain]) {
      if ($userinfo->checkPasswd($options[password])=== true) {
        $ret=$userinfo->setPasswd($options[passwordagain]);

        if ($ret <= 0) {
          if ($ret==0) $title= _("too short password!");
          else if ($ret==-1)
            $title= _("mismatch password !");
          else if ($ret==-2)
            $title= _("not acceptable character found in the password!");
          $options[msg]= _("Password is not changed !");
        } else {
          $title= _("Password is changed !");
          if ($ret < 8)
            $options[msg]=_("Password is too simple to use as a password !");
        }
      } else {
        $title= _("Invalid password !");
        $options[msg]=_("Password is not changed !");
      }
    }
    if (isset($options[user_css]))
      $userinfo->info[css_url]=$options[user_css];
    if (isset($options[email]))
      $userinfo->info[email]=$options[email];
    if ($options[username])
      $userinfo->info[name]=$options[username];
    $udb->saveUser($userinfo);
    $options[css_url]=$options[user_css];
    if (!isset($options[msg]))
      $options[msg]=_("Profiles are saved successfully !");
  }

  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $formatter->send_page("Back to UserPreferences");
  $formatter->send_footer("",$options);
}

function macro_Include($formatter,$value="") {
  global $DBInfo;
  static $included=array();

  if ($formatter->gen_pagelinks) return '';

  preg_match("/([^'\",]+)(?:\s*,\s*)?(\"[^\"]*\"|'[^']*')?$/",$value,$match);
  if ($match) {
    $value=trim($match[1]);
    if ($match[2])
      $title="=== ".substr($match[2],1,-1)." ===\n";
  }

  if ($value and !in_array($value, $included) and $DBInfo->hasPage($value)) {
    $ipage=$DBInfo->getPage($value);
    $ibody=$ipage->_get_raw_body();
    $opt[nosisters]=1;
    ob_start();
    $formatter->send_page($title.$ibody,$opt);
    $out= ob_get_contents();
    ob_end_clean();
    return $out;
  } else {
    return "[[Include($value)]]";
  }
}

function macro_RandomPage($formatter,$value="") {
  global $DBInfo;
  $pages = $DBInfo->getPageLists();

  $test=preg_match("/^(\d+)\s*,?\s*(simple|nobr)?$/",$value,$match);
  if ($test) {
    $count= (int) $match[1];
    $mode=$match[2];
  }
  #$count= (int) $value;
  if ($count <= 0) $count=1;
  $counter= $count;

  $max=sizeof($pages);

  while ($counter > 0) {
    $selected[]=rand(1,$max);
    $counter--;
  }

  foreach ($selected as $item) {
    $selects[]=$formatter->link_tag($pages[$item]);
  }

  if ($count > 1) {
    if (!$mode)
      return "<ul>\n<li>".join("</li>\n<li>",$selects)."</li>\n</ul>";
    if ($mode=='simple')
      return join("<br />\n",$selects)."<br />\n";
    if ($mode=='nobr')
      return join(" ",$selects);
  }
  return join("",$selects);
}

function macro_RandomQuote($formatter,$value="") {
  global $DBInfo;
  define(QUOTE_PAGE,'FortuneCookies');

  if ($value and $DBInfo->hasPage($value))
    $fortune=$value;
  else
    $fortune=QUOTE_PAGE;

  $page=$DBInfo->getPage($fortune);
  $raw=$page->get_raw_body();
 
  $lines=explode("\n",$raw);

  foreach($lines as $line) {
    if (preg_match("/^\s\* (.*)$/",$line,$match))
      $quotes[]=$match[1];
  } 

  $quote=$quotes[rand(1,sizeof($quotes))];

  ob_start();
  $options[nosisters]=1;
  $formatter->send_page($quote,$options);
  $out= ob_get_contents();
  ob_end_clean();
  return $out;
}

function macro_UploadFile($formatter,$value="") {
   $url=$formatter->link_url($formatter->page->urlname);
   $form= <<<EOF
<form enctype="multipart/form-data" method='post' action='$url'>
   <input type='hidden' name='action' value='UploadFile' />
   <input type='file' name='upfile' size='30' />
   <input type='submit' value='Upload' /><br />
   <input type='radio' name='replace' value='1' />Replace original file<br />
   <input type='radio' name='replace' value='0' checked='checked' />Rename if already exist file<br />
</form>
EOF;

   if (!in_array('UploadedFiles',$formatter->actions))
     $formatter->actions[]='UploadedFiles';

   return $form;
}

function do_uploadedfiles($formatter,$options) {
  $list=macro_UploadedFiles($formatter,$options[page],$options);

  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);

  print $list;
  $args[editable]=0;
  $formatter->send_footer($args,$options);
  return;
}

function macro_UploadedFiles($formatter,$value="",$options="") {
   global $DBInfo;

   if ($value and $value!='top') {
      $key=$DBInfo->pageToKeyname($value);
      if ($key != $value)
        $prefix=$formatter->link_url($value,"?action=download&amp;value=");
      $dir=$DBInfo->upload_dir."/$key";
   } else {
      $value=$formatter->page->name;
      $key=$DBInfo->pageToKeyname($formatter->page->name);
      if ($key != $formatter->page->name)
        $prefix=$formatter->link_url($formatter->page->name,"?action=download&amp;value=");
      $dir=$DBInfo->upload_dir."/$key";
   }
   if ($options[value]!='top' and file_exists($dir))
      $handle= opendir($dir);
   else {
      $key='';
      $dir=$DBInfo->upload_dir;
      $handle= opendir($dir);
   }

   $upfiles=array();
   $dirs=array();

   while ($file= readdir($handle)) {
      if (is_dir($dir."/".$file)) {
        if ($file=='.' or $file=='..' or $options[value]!='top') continue;
        $dirs[]= $DBInfo->keyToPagename($file);
        continue;
      }
      $upfiles[]= $file;
   }
   closedir($handle);
   if (!$upfiles and !$dirs) return "<h3>No files uploaded</h3>";
   sort($upfiles); sort($dirs);

   $out="<form method='post' >";
   $out.="<input type='hidden' name='action' value='DeleteFile' />\n";
   if ($key)
     $out.="<input type='hidden' name='value' value='$value' />\n";
   $out.="<table border='0' cellpadding='2'>\n";
   $out.="<tr><th colspan='2'>File name</th><th>Size(byte)</th><th>Date</th></tr>\n";
   $idx=1;
   foreach ($dirs as $file) {
      $link=$formatter->link_url($file,"?action=uploadedfiles",$file);
      $date=date("Y-m-d",filemtime($dir."/".$DBInfo->pageToKeyname($file)));
      $out.="<tr><td class='wiki'>&nbsp;</td><td class='wiki'><a href='$link'>$file</a></td><td align='right' class='wiki'>&nbsp;</td><td class='wiki'>$date</td></tr>\n";
      $idx++;
   }

   if (!$dirs) {
      $link=$formatter->link_tag($value,"?action=uploadedfiles&amp;value=top","..");
      $date=date("Y-m-d",filemtime($dir."/.."));
      $out.="<tr><td class='wiki'>&nbsp;</td><td class='wiki'>$link</td><td align='right' class='wiki'>&nbsp;</td><td class='wiki'>$date</td></tr>\n";
   }

   if (!$prefix) $prefix=$DBInfo->url_prefix."/".$dir."/";

   foreach ($upfiles as $file) {
      $link=$prefix.rawurlencode($file);
      $size=filesize($dir."/".$file);
      $date=date("Y-m-d",filemtime($dir."/".$file));
      $out.="<tr><td class='wiki'><input type='checkbox' name='files[$idx]' value='$file' /></td><td class='wiki'><a href='$link'>$file</a></td><td align='right' class='wiki'>$size</td><td class='wiki'>$date</td></tr>\n";
      $idx++;
   }
   $idx--;
   $out.="<tr><th colspan='2'>Total $idx files</th><td></td><td></td></tr>\n";
   $out.="</table>
Password: <input type='password' name='passwd' size='10' />
<input type='submit' value='Delete selected files' /></form>\n";

   if (!$value and !in_array('UploadFile',$formatter->actions))
     $formatter->actions[]='UploadFile';
   return $out;
}

function macro_Css($formatter="") {
  global $DBInfo;
  $out="
<form method='post'>
<input type='hidden' name='action' value='css' />
  <b>Select a CSS</b>&nbsp;
<select name='user_css'>
";
  $handle = opendir($DBInfo->css_dir);
  $css=array();
  while ($file = readdir($handle)) {
     if (preg_match("/\.css$/i", $file,$match))
        $css[]= $file;
  }

  foreach ($css as $item)
     $out.="<option value='$DBInfo->url_prefix/$DBInfo->css_dir/$item'>$item</option>\n";


  $out.="
    </select>&nbsp; &nbsp; &nbsp;
    <input type='submit' name='save' value='Change CSS' /> &nbsp;";

  $out.="
    <input type='submit' name='clear' value='Clear CSS cookie' /> &nbsp;";

  $out.="</form>\n";
  return $out;
}

function macro_Date($formatter,$value) {
  if (!$value) {
    return date('Y/m/d');
  }
  if ($value[10]== 'T') {
    $value[10]=' ';
    $time=strtotime($value." GMT");
    return date("Y/m/d",$time);
  }
  return date("Y/m/d");
}

function macro_DateTime($formatter,$value) {
  if (!$value) {
    return date('Y/m/d');
  }
  if ($value[10]== 'T') {
    $value[10]=' ';
    $time=strtotime($value." GMT");
    return date("Y/m/d H:i:s",$time);
  }
  return date("Y/m/d\TH:i:s");
}

function macro_UserPreferences($formatter="") {
  global $DBInfo;
  global $HTTP_COOKIE_VARS;

  $user=new User(); # get from COOKIE VARS
  $url=$formatter->link_url("UserPreferences");

  if ($user->id == "Anonymous")
     return <<<EOF
<form method="post" action="$url">
<input type="hidden" name="action" value="userform" />
<table border="0">
  <tr><td>&nbsp;</td></tr>
  <tr><td><b>ID</b>&nbsp;</td><td><input type="text" size="40" name="login_id" /></td></tr>
  <tr><td><b>Password</b>&nbsp;</td><td><input type="password" size="20" maxlength="12" name="login_passwd" /></td></tr>

  <tr><td></td><td><input type="submit" name="login" value="Login" /></td></tr>
        
  <tr><td><b>ID</b>&nbsp;</td><td><input type="text" size="40" name="username" value="" /></td></tr>
  <tr>
     <td><b>Password</b>&nbsp;</td><td><input type="password" size="20" maxlength="12" name="password" value="" />
     <b>Password again</b>&nbsp;<input type="password" size="20" maxlength="12" name="passwordagain" value="" /></td></tr>
  <tr><td><b>Mail</b>&nbsp;</td><td><input type="text" size="60" name="email" value="" /></td></tr>
  <tr><td></td><td>
    <input type="submit" name="save" value="make profile" /> &nbsp;
  </td></tr>
</table>
</form>
EOF;

   $udb=new UserDB($DBInfo);
   $user=$udb->getUser($user->id);
   $css=$user->info[css_url];
   $name=$user->info[name];
   $email=$user->info[email];
   return <<<EOF
<form method="post" action="$url">
<input type="hidden" name="action" value="userform" />
<table border="0">
  <tr><td>&nbsp;</td></tr>
  <tr><td><b>ID</b>&nbsp;</td><td>$user->id</td></tr>
  <tr><td><b>Name</b>&nbsp;</td><td><input type="text" size="40" name="username" value="$name" /></td></tr>
  <tr>
     <td><b>Password</b>&nbsp;</td><td><input type="password" size="20" maxlength="8" name="password" value="" />
     <b>New password</b>&nbsp;<input type="password" size="20" maxlength="8" name="passwordagain" value="" /></td></tr>
  <tr><td><b>Mail</b>&nbsp;</td><td><input type="text" size="60" name="email" value="$email" /></td></tr>
  <tr><td><b>CSS URL </b>&nbsp;</td><td><input type="text" size="60" name="user_css" value="$css" /><br />("None" for disable CSS)</td></tr>
  <tr><td></td><td>
    <input type="submit" name="save" value="save profile" /> &nbsp;
    <input type="submit" name="logout" value="logout" /> &nbsp;
  </td></tr>
</table>
</form>
EOF;
}

function macro_InterWiki($formatter="") {
  global $DBInfo;

  $out="<table border=0 cellspacing=2 cellpadding=0>";
  foreach (array_keys($DBInfo->interwiki) as $wiki) {
    $href=$DBInfo->interwiki[$wiki];
    if (strpos($href,'$PAGE') === false)
      $url=$href.'RecentChanges';
    else {
      $url=str_replace('$PAGE','index',$href);
      #$href=$url;
    }
    $out.="<tr><td><tt><a href='$url'>$wiki</a></tt><td><tt>";
    $out.="<a href='$href'>$href</a></tt></tr>\n";
  }
  $out.="</table>\n";
  return $out;
}


function toutf8($uni) {
  $utf[0]=0xe0 | ($uni >> 12);
  $utf[1]=0x80 | (($uni >> 6) & 0x3f);
  $utf[2]=0x80 | ($uni & 0x3f);
  return chr($utf[0]).chr($utf[1]).chr($utf[2]);
}

function get_key($name) {
  global $DBInfo;
  if (preg_match('/[a-z0-9]/i',$name[0])) {
     return strtoupper($name[0]);
  }
  $utf="";
  if (function_exists ("iconv")) {
    # XXX php 4.1.x did not support unicode sting.
    $utf=iconv($DBInfo->charset,'utf-8',$name);
    $name=$utf;
  }

  if ($utf or $DBInfo->charset=='utf-8') {
    if ((ord($name[0]) & 0xF0) == 0xE0) { # Now only 3-byte UTF-8 supported
       #$uni1=((ord($name[0]) & 0x0f) <<4) | ((ord($name[1]) & 0x7f) >>2);
       $uni1=((ord($name[0]) & 0x0f) <<4) | (($name[1] & 0x7f) >>2);
       $uni2=((ord($name[1]) & 0x7f) <<6) | (ord($name[2]) & 0x7f);

       $uni=($uni1<<8)+$uni2;
       # Hangul Syllables
       if ($uni>=0xac00 && $uni<=0xd7a3) {
         $ukey=0xac00 + (int)(($uni - 0xac00) / 588) * 588;
         $ukey=toutf8($ukey);
         if ($utf)
           return iconv('utf-8',$DBInfo->charset,$ukey);
         return $ukey;
       }
    }
    return '~';
  } else {
    if (preg_match('/[a-z0-9]/i',$name[0])) {
       return strtoupper($name[0]);
    }
    # php does not have iconv() EUC-KR assumed
    # (from NoSmoke monimoin)
    $korean=array('가','나','다','라','마','바','사','아',
                  '자','차','카','타','파','하',"\xca");
    $lastPosition='~';

    $letter=substr($name,0,2);
    foreach ($korean as $position) {
       if ($position > $letter)
           return $lastPosition;
       $lastPosition=$position;
    }
    return '~';
  }
}

function macro_LikePages($formatter="",$args="",$opts=array()) {
  global $DBInfo;

  $pname=_preg_escape($args);

  $metawiki=$opts[metawiki];

  if (strlen($pname) < 3) {
    $opts[msg] = 'Use more specific text';
    return '';
  }

  $s_re="^[A-Z][a-z0-9]+";
  $e_re="[A-Z][a-z0-9]+$";

  $count=preg_match("/(".$s_re.")/",$pname,$match);
  if ($count) {
    $start=$match[1];
    $s_len=strlen($start);
  }
  $count=preg_match("/(".$e_re.")/",$pname,$match);
  if ($count) {
    $end=$match[1];
    $e_len=strlen($end);
  }

  if (!$start && !$end) {
    preg_match("/^(.{2,4})/",$args,$match);
    $s_len=strlen($match[1]);
    $start=_preg_escape($match[1]);
  }

  if (!$end) {
    $end=substr($args,$s_len);
    preg_match("/(.{2,6})$/",$end,$match);
    $end=$match[1];
    $e_len=strlen($end);
    if ($e_len < 2) $end="";
    else $end=_preg_escape($end);
  }

  $starts=array();
  $ends=array();
  $likes=array();

  if (!$metawiki) {
    $pages = $DBInfo->getPageLists();
  } else {
    if (!$end) $needle=$start;
    else $needle="$start|$end";
    $pages = $DBInfo->metadb->getLikePages($needle);
  }
  
  if ($start) {
    foreach ($pages as $page) {
      preg_match("/^$start/",$page,$matches);
      if ($matches)
        $starts[$page]=1;
    }
  }

  if ($end) {
    foreach ($pages as $page) {
      preg_match("/$end$/",$page,$matches);
      if ($matches)
        $ends[$page]=1;
    }
  }

  if ($start || $end) {
    if (!$end) $similar_re=$start;
    else $similar_re="$start|$end";

    foreach ($pages as $page) {
      preg_match("/($similar_re)/i",$page,$matches);
      if ($matches && !$starts[$page] && !$ends[$page])
        $likes[$page]=1;
    }
  }

  $idx=1;
  $hits=0;
  $out="";
  if ($likes) {
    arsort($likes);

    $out.="<h3>These pages share a similar word...</h3>";
    $out.="<ol>\n";
    while (list($pagename,$i) = each($likes)) {
      $pageurl=_rawurlencode($pagename);
      $out.= '<li>' . $formatter->link_tag($pageurl,"",$pagename,"tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol>\n";
    $hits=count($likes);
  }
  if ($starts || $ends) {
    arsort($starts);

    $out.="<h3>These pages share an initial or final title word...</h3>";
    $out.="<table border='0' width='100%'><tr><td width='50%' valign='top'>\n<ol>\n";
    while (list($pagename,$i) = each($starts)) {
      $pageurl=_rawurlencode($pagename);
      $out.= '<li>' . $formatter->link_tag($pageurl,"",$pagename,"tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol></td>\n";

    arsort($ends);

    $out.="<td width='50%' valign='top'><ol>\n";
    while (list($pagename,$i) = each($ends)) {
      $pageurl=_rawurlencode($pagename);
      $out.= '<li>' . $formatter->link_tag($pageurl,"",$pagename,"tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol>\n</td></tr></table>\n";
    $opts[extra]="If you can't find this page, ";
    $hits+=count($starts) + count($ends);
  }

  if (!$hits) {
    $out.="<h3>"._("No similar pages found")."</h3>";
    $opts[extra]=_("You are strongly recommened to find it in MetaWikis. ");
  }

  $opts[msg] = sprintf(_("Like \"%s\""),$args);

  $tag=$formatter->link_to("?action=LikePages&amp;metawiki=1",_("Search all MetaWikis"));
  $opts[extra].="$tag (Slow Slow)<br />";

  return $out;
}


function macro_PageCount($formatter="") {
  global $DBInfo;

  return $DBInfo->getCounter();
}

function macro_PageHits($formatter="") {
  global $DBInfo;

  $pages = $DBInfo->getPageLists();
  sort($pages);
  $hits= array();
  foreach ($pages as $page) {
    $hits[$page]=$DBInfo->counter->pageCounter($page);
  }
  arsort($hits);
  while(list($name,$hit)=each($hits)) {
    if (!$hit) $hit=0;
    $name=$formatter->link_tag($name);
    $out.="<li>$name . . . . [$hit]</li>\n";
  }
  return "<ol>\n".$out."</ol>\n";
}

function macro_PageLinks($formatter="",$options="") {
  global $DBInfo;
  $pages = $DBInfo->getPageLists();

  $out="<ul>\n";
  $cache=new Cache_text("pagelinks");
  foreach ($pages as $page) {
    $p= new WikiPage($page);
    $f= new Formatter($p);
    $out.="<li>".$f->link_to().": ";
    $links=$f->get_pagelinks();
    $links=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$links);
    $out.=$links."</li>\n";
  }
  $out.="</ul>\n";
  return $out;
}

function macro_WantedPages($formatter="",$options="") {
  global $DBInfo;
  $pages = $DBInfo->getPageLists();

  $cache=new Cache_text("pagelinks");
  foreach ($pages as $page) {
    $p= new WikiPage($page);
    $f= new Formatter($p);
    $links=$f->get_pagelinks();
    if ($links) {
      $lns=explode("\n",$links);
      foreach($lns as $link) {
        if (!$link or $DBInfo->hasPage($link)) continue;
        if ($link and !$wants[$link])
          $wants[$link]="[\"$page\"]";
        else $wants[$link].=" [\"$page\"]";
      }
    }
  }

  asort($wants);
  $out="<ul>\n";
  while (list($name,$owns) = each($wants)) {
    $owns=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$owns);
    $out.="<li>".$formatter->link_repl($name). ": $owns</li>";
  }
  $out.="</ul>\n";
  return $out;
}


function macro_PageList($formatter,$arg="") {
  global $DBInfo;

  preg_match("/((\s*,\s*)?date)$/",$arg,$match);
  if ($match) {
    $options[date]=1;
    $arg=substr($arg,0,-strlen($match[1]));
  }
  $needle=_preg_search_escape($arg);

  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
    # show error message
    return "[[PageList(<font color='red'>Invalid \"$arg\"</font>)]]";
  }

  $all_pages = $DBInfo->getPageLists($options);
  $hits=array();

  if ($options[date]) {
    if ($needle) {
      while (list($pagename,$mtime) = @each ($all_pages)) {
        preg_match("/$needle/",$pagename,$matches);
        if ($matches) $hits[$pagename]=$mtime;
      }
    } else $hits=$all_pages;
    arsort($hits);
    while (list($pagename,$mtime) = @each ($hits)) {
      $out.= '<li>'.$formatter->link_tag($pagename).". . . . [".date("Y-m-d",$mtime)."]</li>\n";
    }
    $out="<ol>\n".$out."</ol>\n";
  } else {
    foreach ($all_pages as $page) {
      preg_match("/$needle/",$page,$matches);
      if ($matches) $hits[]=$page;
    }
    sort($hits);
    foreach ($hits as $pagename) {
      $out.= '<li>' . $formatter->link_tag($pagename)."</li>\n";
    }
    $out="<ul>\n".$out."</ul>\n";
  }

  return $out;
}

function macro_TitleIndex($formatter="") {
  global $DBInfo;

  $all_pages = $DBInfo->getPageLists();
  sort($all_pages);

  $key=-1;
  $out="";
  $keys=array();
  foreach ($all_pages as $page) {
    $pkey=get_key($page);
#       $key=strtoupper($page[0]);
    if ($key != $pkey) {
       if ($key !=-1)
          $out.="</UL>";
       $key=$pkey;
       $keys[]=$key;
       $out.= "<a name='$key' /><h3><a href='#top'>$key</a></h3>\n";
       $out.= "<UL>";
    }
    
    $out.= '<LI>' . $formatter->link_tag(_rawurlencode($page),"",$page);
  }
  $out.= "</UL>";

  $index="";
  foreach ($keys as $key)
    $index.= "| <a href='#$key'>$key</a> ";
  $index[0]=" ";
  
  return "<center><a name='top' />$index</center>\n$out";
}

function macro_Icon($formatter="",$value="",$extra="") {
  global $DBInfo;

  $out=$DBInfo->imgs_dir."/$value";
  $out="<img src='$out' border='0' alt='icon' align='middle' />";
  return $out;
}

function macro_RecentChanges($formatter="",$value="") {
  global $DBInfo;
  define(MAXSIZE,5000);
  $new=1;

  $template=
  '$out.= "$icon&nbsp;&nbsp;$title $date . . . . $user $count $extra<br />\n";';
  $use_day=1;

  preg_match("/(\d+)?(?:\s*,\s*)?(.*)?$/",$value,$match);
  if ($match) {
    $size=(int) $match[1];
    $args=explode(",",$match[2]);

    if (in_array ("quick", $args)) $quick=1;
    if (in_array ("nonew", $args)) $checknew=0;
    if (in_array ("showhost", $args)) $showhost=1;
    if (in_array ("comment", $args)) $comment=1;
    if (in_array ("simple", $args)) {
      $use_day=0;
      $template=
  '$out.= "$icon&nbsp;&nbsp;$title @ $day $date by $user $count $extra<br />\n";';
    }
  }
  if ($size > MAXSIZE) $size=MAXSIZE;

  $user=new User(); # retrive user info
  if ($user->id == 'Anonymous')
    $bookmark= $user->bookmark;
  else {
    $udb=new UserDB($DBInfo);
    $userinfo= $udb->getUser($user->id);
    $bookmark= $userinfo->info[bookmark];
  }
  if (!$bookmark) $bookmark=time();

  if ($quick)
    $lines= $DBInfo->editlog_raw_lines($size,1);
  else
    $lines= $DBInfo->editlog_raw_lines($size);
    
  $time_current= time();
  $secs_per_day= 60*60*24;
  $days_to_show= 30;
  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

  foreach ($lines as $line) {
    $parts= explode("\t", $line,3);
    $page_key= $parts[0];
    $ed_time= $parts[2];

    $day = date('Ymd', $ed_time);
    if ($day != $ratchet_day) {
      $ratchet_day = $day;
      unset($logs);
    }

    if ($editcount[$page_key]) {
      if ($logs[$page_key]) {
        $editcount[$page_key]++;
        continue;
      }
      continue;
    }
    $editcount[$page_key]= 1;
    $logs[$page_key]= 1;
  }
  unset($logs);

  $out="";
  $ratchet_day= FALSE;
  foreach ($lines as $line) {
    $parts= explode("\t", $line);
    $page_key=$parts[0];

    if ($logs[$page_key]) continue;

    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $log= $parts[5];
    $act= rtrim($parts[6]);

    if ($ed_time < $time_cutoff)
      break;

    $day = date('Y-m-d', $ed_time);
    if ($use_day and $day != $ratchet_day) {
      $out.=sprintf("<br /><font size='+1'>%s </font> <font size='-1'>[", date($DBInfo->date_fmt, $ed_time));
      $out.=$formatter->link_tag($formatter->page->name,
                                 "?action=bookmark&amp;time=$ed_time",
                                 _("set bookmark"))."]</font><br />\n";
      $ratchet_day = $day;
    } else
      $day=$formatter->link_to("?action=bookmark&amp;time=$ed_time",$day);

    $pageurl=_rawurlencode($page_name);

    if (!$DBInfo->hasPage($page_name))
      $icon= $formatter->link_tag($pageurl,"?action=diff",$formatter->icon[del]);
    else if ($ed_time > $bookmark) {
      $icon= $formatter->link_tag($pageurl,"?action=diff&amp;date=$bookmark",$formatter->icon[updated]);
      if ($checknew) {
        $p= new WikiPage($page_name);
        $v= $p->get_rev($bookmark);
        if (!$v)
          $icon=
            $formatter->link_tag($pageurl,"?action=info",$formatter->icon['new']);
      }
    } else
      $icon= $formatter->link_tag($pageurl,"?action=diff",$formatter->icon[diff]);

    $title= preg_replace("/((?<=[a-z0-9])[A-Z][a-z0-9])/"," \\1",$page_name);
    $title= $formatter->link_tag($pageurl,"",$title);

    if (! empty($DBInfo->changed_time_fmt))
      $date= date($DBInfo->changed_time_fmt, $ed_time);

    if ($DBInfo->show_hosts) {
      if ($showhost && $user == 'Anonymous')
        $user= $addr;
      else {
        if ($DBInfo->hasPage($user)) {
          $user= $formatter->link_tag($user);
        } else
          $user= $user;
      }
    }
    $count=""; $extra="";
    if ($editcount[$page_key] > 1)
      $count=" [".$editcount[$page_key]." changes]";
    if ($comment && $log)
      $extra="&nbsp; &nbsp; &nbsp; <font size='-1'>$log</font>";

    eval($template);

    $logs[$page_key]= 1;
  }
  return $out;
}

function macro_HTML($formatter,$value) {
  return str_replace("&lt;","<",$value);
}

function macro_BR($formatter) {
  return "<br />\n";
}

function macro_FootNote($formatter,$value="") {
  if (!$value) {# emit all footnotes
    $foots=join("\n",$formatter->foots);
    $foots=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$foots);
    unset($formatter->foots);
    return "<br/><tt class='wiki'>----</tt><br/>\n$foots";
  }

  $formatter->foot_idx++;
  $idx=$formatter->foot_idx;

  $text="[$idx]";
  $idx="fn".$idx;
  if ($value[0] == "*") {
#    $dum=explode(" ",$value,2); XXX
    $p=strrpos($value,'*')+1;
    $text=substr($value,0,$p);
    $value=substr($value,$p);
  } else if ($value[0] == "[") {
    $dum=explode("]",$value,2);
    if (trim($dum[1])) {
       $text=$dum[0]."&#093;"; # make a text as [Alex77]
       $idx=substr($dum[0],1);
       $formatter->foot_idx--; # undo ++.
       if (0 === strcmp($idx , (int)$idx)) $idx="fn$idx";
       $value=$dum[1]; 
    } else if ($dum[0]) {
       $text=$dum[0]."]";
       $idx=substr($dum[0],1);
       $formatter->foot_idx--; # undo ++.
       if (0 === strcmp($idx , (int)$idx)) $idx="fn$idx";
       return "<tt class='foot'><a href='#$idx'>$text</a></tt>";
    }
  }
  $formatter->foots[]="<tt class='foot'>&#160;&#160&#160;".
                      "<a name='$idx'/>".
                      "<a href='#r$idx'>$text</a>&#160;</tt> ".
                      "$value<br/>";
  return "<tt class='foot'><a name='r$idx'/><a href='#$idx'>$text</a></tt>";
}

function macro_TableOfContents($formatter="",$value="") {
 $head_num=1;
 $head_dep=0;
 $TOC="\n<div class='toc'><a name='toc' id='toc' /><dl><dd><dl>\n";

 $formatter->toc=1;
 $lines=explode("\n",$formatter->page->get_raw_body());
 foreach ($lines as $line) {
   $line=preg_replace("/\n$/", "", $line); # strip \n
   preg_match("/(?<!=)(={1,5})\s(#?)(.*)\s+(={1,5})$/",$line,$match);

   if (!$match) continue;

   $dep=strlen($match[1]);
   if ($dep != strlen($match[4])) continue;
   $head=str_replace("<","&lt;",$match[3]);
   # strip some basic wikitags
   # $formatter->baserepl,$head);
   $head=preg_replace($formatter->baserule,"\\1",$head);
   $head=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$head);

   if (!$depth_top) { $depth_top=$dep; $depth=1; }
   else {
     $depth=$dep - $depth_top + 1;
     if ($depth <= 0) $depth=1;
   }

#   $depth=$dep;
#   if ($dep==1) $depth++; # depth 1 is regarded same as depth 2
#   $depth--;

   $num="".$head_num;
   $odepth=$head_dep;
   $open="";
   $close="";

   if ($match[2]) {
      # reset TOC numberings
      $dum=explode(".",$num);
      $i=sizeof($dum);
      for ($j=0;$j<$i;$j++) $dum[$j]=1;
      $dum[$i-1]=0;
      $num=join($dum,".");
      if ($prefix) $prefix++;
      else $prefix=1;
   }

   if ($odepth && ($depth > $odepth)) {
      $open.="<dd><dl>\n";
      $num.=".1";
   } else if ($odepth) {
      $dum=explode(".",$num);
      $i=sizeof($dum)-1;
      while ($depth < $odepth && $i > 0) {
         unset($dum[$i]);
         $i--;
         $odepth--;
         $close.="</dl></dd>\n";
      }
      $dum[$i]++;
      $num=join($dum,".");
   }
   $head_dep=$depth; # save old
   $head_num=$num;

   $TOC.=$close.$open."<dt><a id='toc$prefix-$num' name='toc$prefix-$num' /><a href='#s$prefix-$num'>$num</a> $head</dt>\n";

  }

  if ($TOC) {
     $close="";
     $depth=$head_dep;
     while ($depth>1) { $depth--;$close.="</dl></dd>\n"; };
     return $TOC.$close."</dl></dd></dl>\n</div>\n";
  }
  else return "";
}

function macro_FullSearch($formatter="",$value="", $opts=array()) {
  global $DBInfo;
  $needle=$value;

  $url=$formatter->link_url($formatter->page->urlname);
  $needle=str_replace('"',"&#34;",$needle);

  $form= <<<EOF
<form method='get' action='$url'>
   <input type='hidden' name='action' value='fullsearch' />
   <input name='value' size='30' value="$needle" />
   <input type='submit' value='Go' /><br />
   <input type='checkbox' name='context' value='20' checked='checked' />Display context of search results<br />
   <input type='checkbox' name='backlinks' value='1' checked='checked' />Search BackLinks only<br />
   <input type='checkbox' name='case' value='1' />Case-sensitive searching<br />
   </form>
EOF;

  if (!$needle) { # or blah blah
     $opts[msg] = 'No search text';
     return $form;
  }
  $needle=_preg_search_escape($needle);
  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
     $opts[msg] = sprintf(_("Invalid search expression \"%s\""), $needle);
     return $form;
  }

  $hits = array();
  $pages = $DBInfo->getPageLists();
  $pattern = '/'.$needle.'/';
  if ($opts['case']) $pattern.="i";

  if ($opts['backlinks']) {
     $opts[context]=0; # turn off context-matching
     $cache=new Cache_text("pagelinks");
     foreach ($pages as $page_name) {
       $links==-1;
       $links=$cache->fetch($page_name);
       if ($links==-1) {
          $p= new WikiPage($page_name);
          $f= new Formatter($p);
          $links=$f->get_pagelinks();
       }
       $count= preg_match_all($pattern, $links, $matches);
       if ($count) {
         $hits[$page_name] = $count;
       }
     }
  } else {
     while (list($_, $page_name) = each($pages)) {
       $p = new WikiPage($page_name);
       if (!$p->exists()) continue;
       $body= $p->_get_raw_body();
       #$count = count(preg_split($pattern, $body))-1;
       $count = preg_match_all($pattern, $body,$matches);
       if ($count) {
         $hits[$page_name] = $count;
         # search matching contexts
         $contexts[$page_name] = find_needle($body,$needle,$opts[context]);
       }
     }
  }
  arsort($hits);

  $out.= "<ul>";
  reset($hits);
  $idx=1;
  while (list($page_name, $count) = each($hits)) {
    $out.= '<li>'.$formatter->link_tag($page_name,
          "?action=highlight&amp;value=$needle",
          $page_name,"tabindex='$idx'");
    $out.= ' . . . . ' . $count . (($count == 1) ? ' match' : ' matches');
    $out.= $contexts[$page_name];
    $out.= "</li>\n";
    $idx++;
  }
  $out.= "</ul>\n";

  $opts[hits]= count($hits);
  $opts[all]= count($pages);
  return $out;
}

function macro_ISBN($formatter="",$value="") {
  $ISBN_MAP="ISBNMap";
  $DEFAULT=<<<EOS
Amazon http://www.amazon.com/exec/obidos/ISBN= http://images.amazon.com/images/P/\$ISBN.01.MZZZZZZZ.gif
Aladdin http://www.aladdin.co.kr/catalog/book.asp?ISBN= http://www.aladdin.co.kr/Cover/\$ISBN_1.gif
EOS;

  $DEFAULT_ISBN="Amazon";
  $re_isbn="/([0-9\-]{9,}[xX]?)(?:\s*,\s*)?([A-Z][a-z]*)?(?:\s*,\s*)?(noimg)?/";

  $test=preg_match($re_isbn,$value,$match);
  if ($test === false)
     return "<p><strong class=\"error\">Invalid ISBN \"%value\"</strong></p>";

  $isbn2=$match[1];
  $isbn=str_replace("-","",$isbn2);

  if ($match[2] && strtolower($match[2][0])=="k")
     $lang="Aladdin";
  else
     $lang=$DEFAULT_ISBN;

  $list= $DEFAULT;
  $map= new WikiPage($ISBN_MAP);
  if ($map->exists)
     $list.=$map->get_raw_body();

  $lists=explode("\n",$list);
  $ISBN_list=array();
  foreach ($lists as $line) {
     if (!$line or !preg_match("/[a-z]/i",$line[0])) continue;
     $dum=explode(" ",$line);
     if (sizeof($dum) == 2)
        $dum[]=$ISBN_list[$DEFAULT_ISBN][0];
     else if (sizeof($dum) !=3) continue;

     $ISBN_list[$dum[0]]=array($dum[1],$dum[2]);
  }

  if ($ISBN_list[$lang]) {
     $booklink=$ISBN_list[$lang][0];
     $imglink=$ISBN_list[$lang][1];
  } else {
     $booklink=$ISBN_list[$DEFAULT_ISBN][0];
     $imglink=$ISBN_list[$DEFAULT_ISBN][1];
  }

  if (strpos($booklink,'$ISBN') === false)
     $booklink.=$isbn;
  else {
     if (strpos($booklink,'$ISBN2') === false)
        $booklink=str_replace('$ISBN',$isbn,$booklink);
     else
        $booklink=str_replace('$ISBN2',$isbn2,$booklink);
  }

  if (strpos($imglink, '$ISBN') === false)
        $imglink.=$isbn;
  else {
     if (strpos($imglink, '$ISBN2') === false)
        $imglink=str_replace('$ISBN', $isbn, $imglink);
     else
        $imglink=str_replace('$ISBN2', $isbn2, $imglink);
  }

  if ($match[3] && $match[3] == 'noimg')
     return $formatter->icon[www]."[<a href='$booklink'>ISBN-$isbn2</a>]";
  else
     return "<a href='$booklink'><img src='$imglink' border='1' title='$lang".
       ": ISBN-$isbn' alt='[ISBN-$isbn2]'></a>";
}

function macro_TitleSearch($formatter="",$needle="",$opts=array()) {
  global $DBInfo;

  $url=$formatter->link_url($formatter->page->name);

  if (!$needle) {
    $opts[msg] = _("Use more specific text");
    return "<form method='get' action='$url'>
      <input type='hidden' name='action' value='titlesearch' />
      <input name='value' size='30' value='$needle' />
      <input type='submit' value='Go' />
      </form>";
  }
  $opts[msg] = sprintf(_("Title search for \"%s\""), $needle);
  $needle=_preg_search_escape($needle);
  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
    $opts[msg] = sprintf(_("Invalid search expression \"%s\""), $needle);
    return "<form method='get' action=''>
      <input type='hidden' name='action' value='titlesearch' />
      <input name='value' size='30' value='$needle' />
      <input type='submit' value='Go' />
      </form>";
  }
  $pages= $DBInfo->getPageLists();
  $hits=array();
  foreach ($pages as $page) {
     preg_match("/".$needle."/i",$page,$matches);
     if ($matches)
        $hits[]=$page;
  }

  sort($hits);

  $out="<ul>\n";
  $idx=1;
  foreach ($hits as $pagename) {
    if ($opts[linkto])
      $out.= '<li>' . $formatter->link_tag($options[page],"$opts[linkto]$pagename",$pagename,"tabindex='$idx'")."</li>\n";
    else
      $out.= '<li>' . $formatter->link_tag($pagename,"","tabindex='$idx'")."</li>\n";
    $idx++;
  }

  $out.="</ul>\n";
  $opts[hits]= count($hits);
  $opts[all]= count($pages);
  return $out;
}

function macro_GoTo($formatter="",$value="") {
  $url=$formatter->link_url($formatter->page->name);
  return "<form method='get' action='$url'>
    <input type='hidden' name='action' value='goto' />
    <input name='value' size='30' value='$value' />
    <input type='submit' value='Go' />
    </form>";
}

function macro_SystemInfo($formatter="",$value="") {
  global $_revision,$_release;

  $version=phpversion();
  $uname=php_uname();
  list($aversion,$dummy)=explode(" ",$_SERVER['SERVER_SOFTWARE'],2);

  $pages=macro_PageCount($formatter);
   
  return <<<EOF
<table border='0' cellpadding='5'>
<tr><th width='200'>PHP Version</th> <td>$version ($uname)</td></tr>
<tr><th>MoniWiki Version</th> <td>Release $_release [$_revision]</td></tr>
<tr><th>Apache Version</th> <td>$aversion</td></tr>
<tr><th>Number of Pages</th> <td>$pages</td></tr>
</table>
EOF;
}

function processor_html($formatter="",$value="") {
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  return $value;
}

function processor_plain($formatter,$value) {
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  $value=str_replace('<','&lt;',$value);
  return "<pre class='code'>$value</pre>";
}

function processor_latex($formatter="",$value="") {
  global $DBInfo;
  # site spesific variables
  $latex="/usr/bin/latex";
  $dvips="dvips";
  $convert="convert";
  $vartmp_dir="/var/tmp";
  $cache_dir="pds/LaTeX";
  $option='-interaction=batchmode ';

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  if (!$value) return;

  if (!file_exists($cache_dir)) {
    umask(000);
    mkdir($cache_dir,0777);
  }

  $tex=$value;

  $uniq=md5($tex);

  $src="\documentclass[10pt,notitlepage]{article}
\usepackage{amsmath}
\usepackage{amsfonts}
%%\usepackage[all]{xy}
\\begin{document}
\pagestyle{empty}
$tex
\end{document}
";

  if ($formatter->refresh || !file_exists("$cache_dir/$uniq.png")) {
     $fp= fopen("$vartmp_dir/$uniq.tex", "w");
     fwrite($fp, $src);
     fclose($fp);

     $outpath="$cache_dir/$uniq.png";

     $cmd= "cd $vartmp_dir; $latex $option $uniq.tex >/dev/null";
     system($cmd);

     $cmd= "cd $vartmp_dir; $dvips -D 600 $uniq.dvi -o $uniq.ps";
     system($cmd);

     $cmd= "$convert -crop 0x0 -density 120x120 $vartmp_dir/$uniq.ps $outpath";
     system($cmd);

     system("rm $vartmp_dir/$uniq.*");
  }
  return "<img src='$DBInfo->url_prefix/$cache_dir/$uniq.png' alt='tex'".
         "title=\"$tex\" />";
}

function processor_php($formatter="",$value="") {
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  $php=$value;
  ob_start();
  highlight_string($php);
  $highlighted= ob_get_contents();
  ob_end_clean();
#  $highlighted=preg_replace("/<code>/","<code style='background-color:#c0c0c0;'>",$highlighted);
#  $highlighted=preg_replace("/<\/?code>/","",$highlighted);
#  $highlighted="<pre style='color:white;background-color:black;'>".
#               $highlighted."\n</pre>";
  return $highlighted;
}

?>
