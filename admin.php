<?php

/**
 * Back-end Miniblog_XH.
 * Copyright (c) 2016 svasti@svasti.de
 *
 * Last change: 20.08.2016 08:53:10
 *
 */


if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}


/**
 * Plugin administration
 */
if (function_exists('XH_registerStandardPluginMenuItems')) {
    XH_registerStandardPluginMenuItems(true);
}

if (!$plugin_cf['miniblog']['posts_from_members']) include_once 'writer.php';

Miniblog_addUrls();


if (function_exists('XH_wantsPluginAdministration')
        && XH_wantsPluginAdministration('miniblog')
        || isset($miniblog) && $miniblog == 'true')
{

    if(!isset($plugin_cf['miniblog']['version'])
        || $plugin_cf['miniblog']['version'] != MINIBLOG_VERSION) {
        if (Miniblog_updateLangFile()) {
            include $pth['folder']['plugins'] . 'miniblog/languages/'. $sl . '.php';
        }
        if ($o .= Miniblog_createConfig()) {
            include $pth['folder']['plugins'] . 'miniblog/config/config.php';
        }
    }
    Miniblog_checkFile();
    $o .= print_plugin_admin('on');

    if(!$admin || $admin == 'plugin_main') {

        $o .= '<h5 style="margin:0;">Miniblog_XH ' . MINIBLOG_VERSION . ' <small><small>&copy; 2016 by'
           . ' <a href="http://svasti.de" target="_blank">svasti</a></small></small></h5>';

        if ($plugin_cf['miniblog']['posts_from_single_files']) $o .= Miniblog_adminPosts();
    }
    if ($admin == 'plugin_config') {
        $o .= $plugin_tx['miniblog']['hint_language_var'];
        if (isset($_POST['mblogarchive'])) $o .= Miniblog_updateArchive();
        if ($plugin_cf['miniblog']['archive_archiving_date'] && $plugin_cf['miniblog']['posts_from_single_files']) {
            $o .= '<form method="post" style="margin:0 0 1em;">'
                . '<input type="submit" name="mblogarchive" value="' . $plugin_tx['miniblog']['update_archive'] . '">'
                . '<label>(' . $plugin_tx['miniblog']['hint_archive_config_page'] . ')</label></form>';
        }
        if (!$plugin_cf['miniblog']['posts_from_single_files'] && $plugin_cf['miniblog']['posts_from_members']) {
            $o .= '<p class="xh_fail">' . $plugin_tx['miniblog']['text_if_member_check_single_file'] . '</p>';
        }
    }
    if ($admin == 'plugin_language') { Miniblog_updateLangFile();
        $o .= $plugin_tx['miniblog']['hint_language_var'];
        if (isset($_POST['mblogarchive'])) $o .= Miniblog_updateArchive();
        if ($plugin_cf['miniblog']['archive_archiving_date'] && $plugin_cf['miniblog']['posts_from_single_files']) {
            $o .= '<form method="post" style="margin:0 0 1em;">'
                . '<input type="submit" name="mblogarchive" value="' . $plugin_tx['miniblog']['update_archive'] . '">'
                . '<label>(' . $plugin_tx['miniblog']['hint_archive_language_page'] . ')</label></form>';
        }
    }

	$o .= plugin_admin_common($action, $admin, $plugin);
}

/**
 * Creates json file with data for memberpostings if it doesn't exist
 */
function Miniblog_checkFile()
{
    global $pth, $plugin_cf;

    // create userfiles under content if missing
    if (!is_file($pth['folder']['content'].'miniblog/miniblog.php')) {
        if (!is_dir($pth['folder']['content'].'miniblog')) {
               if (!mkdir($pth['folder']['content'].'miniblog', 0777, true))
                    e('missing', 'folder', $pth['folder']['content'].'miniblog');
        }
        if (file_put_contents($pth['folder']['content'].'miniblog/miniblog.php','') === FALSE)
            e('missing', 'file', $pth['folder']['content'].'miniblog/miniblog.php');
    }
    if (!is_dir($pth['folder']['content'] . 'extedit')) {
        if (mkdir($pth['folder']['content'] . 'extedit', 0777, true) === FALSE)
            e('missing', 'folder', $pth['folder']['content'].'extedit');
    }
}



/**
 * Adds the blog posts with their URLs to the standard CMSimple_XH internal link list in edit mode
 * Active only from the plugin backend for the admin to edit blog posts
 *
 */
function Miniblog_addUrls()
{
	global $pth, $u, $cl, $h, $l, $c, $f, $plugin_tx;

    $posts = json_decode(file_get_contents($pth['folder']['content'].'miniblog/miniblog.php'), true);
    if ($posts) {
        foreach ($posts as $folder => $postings) {
            foreach ($postings as $post => $postdata) {
                if (isset($postdata['publish']) && $postdata['publish'] && $postdata['publish'] != 'false') {
                    $h[] = $u[] = $plugin_tx['miniblog']['single-file_blogname_in_url'] . '/' . $folder . '/' . $post;
                    //$c[] = $f == 'sitemap' ? '#CMSimple hide#' : '';
                    $l[] = 1;
                    //if ($f == 'sitemap') $cl ++;
                }
            }
        }
    }
}



/**
 * Updates the archive settings of all member postings
 * Also eliminates stray values in the blog post data file
*/
function Miniblog_updateArchive()
{
    global $pth, $e, $plugin_cf, $plugin_tx;
    $cf = $plugin_cf['miniblog'];
    $ptx = $plugin_tx['miniblog'];

    $posts = json_decode(file_get_contents($pth['folder']['content'].'miniblog/miniblog.php'), true);
    // eliminate possible stray values
    foreach ($posts as $key=>$value) {
        if ($key == 'folder') continue;
    	if (!is_array($value) || empty($value)) unset($posts[$key]);
        foreach ($value as $key2=>$value2) {
        	if (!is_array($value2) || empty($value2)) unset($posts[$key][$key2]);
        }
    }

    foreach ($posts as $folder=>$postings) {
        if ($folder == 'folder') continue;
        foreach ($posts[$folder] as $post=>$value) {
            if ($plugin_cf['miniblog']['archive_archiving_date']
                && isset($posts[$folder][$post]['date'])
                && $posts[$folder][$post]['date'] < strtotime($cf['archive_archiving_date'])) {

                if($ptx['single-file_archive_page']) {
                    $posts[$folder][$post]['blog'] = $ptx['single-file_archive_page'];
                    $posts[$folder][$post]['archive'] = false;
                } else {
                    $posts[$folder][$post]['archive'] = true;
                }

            } else {
                if(!isset($posts[$folder][$post]['blog']) ||
                    $posts[$folder][$post]['blog'] == $ptx['single-file_archive_page']) {
                    $blogarray = explode(',',$plugin_tx['miniblog']['single-file_backlinks_to']);
                    $posts[$folder][$post]['blog'] = $blogarray[0];
                }
                $posts[$folder][$post]['archive'] = false;
            }
        }
    }

    if(file_put_contents($pth['folder']['content'].'miniblog/miniblog.php',json_encode($posts), LOCK_EX)) {
        return '<p class="xh_success">Archiv-Werte neu zugeordnet</p>';
    }
}


/**
 * Administration of member postings from the plugin backend *
 */
function Miniblog_adminPosts()
{
    global $pth, $plugin_cf, $plugin_tx, $tx, $bjs;
    $o = '';
    $ptx = $plugin_tx['miniblog'];

    // keep scroll positions of page and file list
    if(isset($_COOKIE['listScroll'])) {
        $bjs .= '<script type="text/javascript">
document.getElementById(\'mbloglist\').scrollTop = ' . $_COOKIE['listScroll'] . ';</script>';
    }
    if(isset($_COOKIE['pageScroll'])) {
        $bjs .= '<script type="text/javascript">
window.scrollTo(0,'.( $_COOKIE['pageScroll'] ).');</script>';
    }

    // read the data file of the blog postings
    if (isset($_POST['mblognewdata'])) $o .= Miniblog_receivePostData();
    $posts = json_decode(file_get_contents($pth['folder']['content'].'miniblog/miniblog.php'), true);
    $posts = Miniblog_includeOrphans($posts);
    $posts = Miniblog_deleteUnusedFolders($posts);

    if ($plugin_cf['miniblog']['posts_from_members']) $o .= Miniblog_showFolders($posts);


    // prepare to open the selected blog file
    if(isset($_GET['posting'])) {
        $get = $_GET['posting'];
        list($folder,$post) = explode('---',$get);
    } else $folder = $user = $post = $get = '';

    $o .= '<h1>' . $ptx['text_file_based_blog_posts'] . ' <input type="image" src="' . $pth['folder']['plugin_css']
        . '/add.png" style="width:14px;height:14px" title="' . $ptx['text_new_blog_post'] . '"
           onClick="var add = document.getElementById(\'add\').style.display;
           if (add != \'inline\') document.getElementById(\'add\').style.display = \'inline\';
           else document.getElementById(\'add\').style.display = \'none\'; " alt="add"></h1>';

    $o .= '<form method="post" style="display:none;" id="add">'
        . '<label>' . $ptx['text_new_post'] . ':</label> <span class="mblog_nowrap"><label>'
        . $ptx['text_folder']
        . '</label> '
        . '<input type="text" name="mblogfolder" style="width:initial"></span>'
        . ' <span class="mblog_nowrap"><label>'
        . $ptx['text_short_blog_title'] . '</label> '
        . '<input type="text" name="mblogpost" style="width:initial">'
        . '<input type="submit" value="' . $tx['action']['ok'] . '"
           onClick="var folder = document.getElementsByName(\'mblogfolder\')[0].value;
var post = document.getElementsByName(\'mblogpost\')[0].value;
var text = \': ' . $ptx['error_allowed_chars2'] . '\';
if (!post && !folder) return true;
if (!post || /\W/g.test(post)) {
  alert(\'' . $ptx['text_short_blog_title'] . '\' + text);
  return false;
}
if (!folder || /\W/g.test(folder)) {
  alert(\'' . $ptx['text_folder'] . '\' + text);
  return false;
}
if (folder && post)
document.getElementById(\'add\').action = \'?&miniblog&normal&posting=\' + folder + \'---\' + post;"  >'
        . '</span></form>';

    $o .= '<div class="mblog_list" id="mbloglist">';

    if ($posts) {
        ksort($posts);
        foreach ($posts as $onefolder => $folderposts) {
            if ($onefolder == 'folder') continue;
            ksort($folderposts);
            foreach ($folderposts as $onepost => $onepostdata) {
                $start = $end = '';
                if ($folder == $onefolder && $post == $onepost) {
                    $start .= '<form method="post" action="?&miniblog&normal">'
                            . '<p class="mblog_selectedPost">';
                    $end   .= ' <input type="image" src="' . $pth['folder']['plugin_css']
                            . '/delete.png" style="vertical-align:sub;width:14px;height:14px" onClick="if(confirm(\''
                            . $ptx['text_confirm_delete']
                            . '\')) return true; else return false;"
                               title="' . $ptx['text_delete']
                            . '" alt="' . $ptx['text_alt_delete'] . '">'
                            . '<input type="hidden" name="mblognewdata" value="true">'
                            . '<input type="hidden" name="mblogdelpost" value="' . $onepost . '">'
                            . '<input type="hidden" name="mblogfolder" value="' . $onefolder . '">'
                            . '</p></form>';
                } else {
                    $start .= '<p>' . a('','&miniblog&normal&posting='.$onefolder.'---'. $onepost
                            . '" OnClick="
    document.cookie = \'listScroll=\' + document.getElementById(\'mbloglist\').scrollTop;');
                    $end   .= '</a></p>';
                }
                $o .= "\n";
                $o .= $start;
                $o .= '<span>'. $onefolder . '/' . $onepost . '</span> ';

                if(isset($onepostdata['title']))  {
                    $o .= '<b> ' . $onepostdata['name'] . ':</b> ';
                    $o .= '<i>'
                        . $onepostdata['title'];
                    $o .= $onepostdata['cat'] ? ' (' . str_replace('__', ' ', $onepostdata['cat']) .')' : '';
                    $o .= '</i>';
                    $o .= $onepostdata['date'] > 10000
                        ? ' ' . date($plugin_cf['miniblog']['date_format'], (int)$onepostdata['date'])
                        : '';
                    $blog = isset($onepostdata['blog']) && $onepostdata['blog']
                        ? ' ' . $onepostdata['blog']
                        : '';
                    $o .= $onepostdata['publish'] && $onepostdata['publish'] != 'false'
                        ? ', (' . $ptx['text_publ'] . $blog . ')'
                        : '';
                }
                $o .= $end;
            }
        }
    }
    $o .= '</div>';

    if($get) {
        if(isset($_POST['editposting'])) {
            file_put_contents($pth['folder']['content'] . 'extedit/' . $get . '.htm',$_POST['editposting']);
        }
        $posting = is_file($pth['folder']['content'] . 'extedit/' . $get . '.htm')
                 ? file_get_contents($pth['folder']['content'] . 'extedit/' . $get . '.htm')
                 : '';

        $name = isset($posts[$folder][$post]['name'])   ? $posts[$folder][$post]['name']    : '';
        $o .= Miniblog_viewEditHeader($folder, $name, $post, $posts, true);

        $o .= '<span id="warning"></span>';

        // switch editing the blog posting on or off
        if(isset($_GET['editposting'])) {
            $o .= a('','&miniblog&normal&posting='
                . $folder.'---'. $post . '" OnClick="
document.cookie = \'pageScroll=\' + document.documentElement.scrollTop;')
                . $plugin_tx['miniblog']['text_mode_view'] . '</a>';
            $o .= '<form method="POST">';
            $o .= '<textarea name="editposting" cols="80"'
                . ' rows="25" class="xh-editor" style="width: 100%">'
                . $posting
                . '</textarea></form>';
            init_editor(array('xh-editor'), false);

        } else {
        $o .=  a('','&miniblog&normal&posting=' . $folder . '---' . $post . '&editposting=true')
            . $plugin_tx['miniblog']['text_mode_edit'] . '</a>';
        $o .= evaluate_scripting($posting) ;
        if ($plugin_cf['miniblog']['comments_plugin']
             && isset($posts[$folder][$post]['comments'])
             && $posts[$folder][$post]['comments'] == 'true')
            $o .= evaluate_scripting('{{{' . $plugin_cf['miniblog']['comments_plugin']
                . ' "' . $folder . '---' . $post . '"}}}');
        }
    }

    // js for the filebrowser
    $editorbrowser = defined('CMSIMPLE_XH_VERSION')
    && version_compare(CMSIMPLE_XH_VERSION, "CMSimple_XH 1.7", ">=")
    ? $pth['folder']['base'].'?filebrowser=editorbrowser&editor'
    : $pth['folder']['plugins'].'filebrowser/editorbrowser.php?editor';

    $bjs .= '<script type="text/javascript">
function filebrowser (type) {
    window.open("'.$editorbrowser
    . '=miniblog&prefix='.$pth['folder']['base'].'&base=./&type=" + type, "",
    "toolbar=no,location=no,status=no,menubar=no," +
    "scrollbars=yes,resizable=yes,width=640,height=480");
}
function insertURI(url) {
    url = url.replace("'.$pth['folder']['images'].'","");
    document.getElementsByName("mblogimg")[0].value = url;
}
</script>';

    return $o;
}



function Miniblog_deleteUnusedFolders($posts)
{
    foreach ($posts as $key=>$value) {
        if (!$value) unset($posts[$key]);
    }
    return $posts;
}



/**
 * create button/link for editing the memberfolders
 */
function Miniblog_showFolders($posts)
{
    global $plugin_tx;

    $o = '<p>';
    if (isset($_GET['editfolders'])) {
        $o .= a('','&miniblog&normal') . $plugin_tx['miniblog']['text_close_user_folder_mapping'] . '</a></form>';
        $o .= Miniblog_editFolders($posts);
    } else {
        $o .= a('','&miniblog&normal&editfolders') . $plugin_tx['miniblog']['text_edit_user_folder_mapping'] . '</a>';
    }
    return $o . '</p>';
}



function Miniblog_editFolders($posts)
{
	global $plugin_tx, $pth, $tx;
    $o = '';
    $folderlist = array();
    $ptx = $plugin_tx['miniblog'];

    $folderlist = $posts['folder']; 
    natsort($folderlist);
    $o .= '<div class="mblog_folder"><div><p><span>'
        . $ptx['text_folder']
        . '</span><span>' . $ptx['text_user_name'] . '</span></div></p>';
    foreach ($folderlist as $key=>$value) {
        $o .= '<form method="post">'
            . '<input type="image" src="' . $pth['folder']['plugins']
            . 'miniblog/css/delete.png" name="mblogdelfolder" style="height:14px;width14px;" title="'
            . $ptx['text_delete'] . '">'
            . '<input type="text" name="mblognewfolder" style="text-align:center;width:calc(50% - 20px);" value="'
            . $value .'">'
            . '<div style="display:inline-block;text-align:center;width:calc(50% - 3em - 2px);">'
            . $key .'</div>'
            . '<input type="hidden" name="mblogfolderuser" value="'
            . $key .'">'
            . '<input type="submit" value="' . $tx['action']['ok'] . '" style="width:3em" title="'
            . $ptx['text_save_changes'] . '">'
            . '<input type="hidden" name="mblognewdata" value="true">'
            . '</form>';
    }
    $o .= '<form method="post">'
        . '<img src="' . $pth['folder']['plugins']
        . 'miniblog/css/arrow.png" style="height:14px;width14px;margin:0;" title="'
        . $plugin_tx['miniblog']['text_new_folder_user_mapping'] . '">'
        . '<input type="text" name="mblognewfolder" style="width:calc(50% - 20px);">'
        . '<input type="text" name="mblogfolderuser" style="width:calc(50% - 3em - 6px);">'
        . '<input type="submit" value="' . $tx['action']['ok'] . '" style="width:3em" title="'
        . $ptx['text_save_changes'] . '">'
        . '<input type="hidden" name="mblognewdata" value="true">'
        . '</form>';

    $strayfolderlist = $usedfolders = array();
    foreach ($posts as $folder=>$posting) {
        if ($folder == 'folder') continue;
        $usedfolders[] = $folder;
        if (!in_array($folder,$folderlist)) $strayfolderlist[] = $folder;
    }

    natsort($usedfolders);
    $o .= '<div><p>' . sprintf($ptx['text_usage_of_folder'], count($usedfolders)) . '</p></div>';
    $o .= implode(', ',$usedfolders);

    if($strayfolderlist) {
        natsort($strayfolderlist);
        $o .= '<div><p>' . count($strayfolderlist) . ' '
            . $ptx['text_posts_without_user'] . '</p></div>';
        $o .= implode(', ',$strayfolderlist);
    }
    $o .= '</div>';

    return $o;
}


/**
 * Reads important values from the old language file und updates these
 * files with default files keeping the important values
 */
function Miniblog_updateLangFile()
{
	global $pth;
    $langfiles = array();
    $defaultfiles = array('en');

    $langdir = scandir($pth['folder']['plugins'] . 'miniblog/languages');
    foreach ($langdir as $file) {
        if(strlen($file) == 6) $langfiles[] = str_replace('.php','',$file);
        if(strpos($file, '_default.php')) $defaultfiles[] = str_replace('_default.php','',$file);
    }
    foreach ($defaultfiles as $value) {
        $defaultlang = $value == 'en'
            ? file_get_contents($pth['folder']['plugins'] . 'miniblog/languages/default.php')
            : file_get_contents($pth['folder']['plugins'] . 'miniblog/languages/' . $value . '_default.php');
        if (in_array($value, $langfiles)) {
            $lang = file_get_contents($pth['folder']['plugins'] . 'miniblog/languages/' . $value . '.php');

            $defaultlang = Miniblog_updateVar($lang,$defaultlang, array(
                'page-posts_backlinks_from',
                'page-posts_backlinks_to',
                'single-file_blogname_in_url',
                'single-file_category_list',
                'single-file_backlinks_to',
                'single-file_archive_page'
                ));
        }
        if (!file_put_contents($pth['folder']['plugins'] . 'miniblog/languages/' . stsl($value) . '.php', $defaultlang))
            e('cntwriteto', 'file', $pth['folder']['plugins'] . 'miniblog/languages/' . $value . '.php');
    }
}
/**
 * Helper function for updating language files, reads a valuearray from a file string und puts it into another file string
 *
 */
function Miniblog_updateVar($lang, $defaultlang, $vararray)
{
	foreach ($vararray as $value) {
        preg_match('!\[\'' . $value . '\'\]="(.*)"!',$lang,$matches);
        $var1 = isset($matches[1])? trim($matches[1]) : '';
        if ($var1) {
            $defaultlang = preg_replace('!\[\'' . $value . '\'\]="(.*)"!','[\'' . $value . '\']="' . $var1 . '"', $defaultlang);
        }
    }
    Return $defaultlang;
}



/**
 * Easy updating, old config file is first read, then replaced by new one
 */
function Miniblog_createConfig()
{
	global $pth ,$plugin_tx;

    // make sure that the plugin css really gets put into the generated plugincss
    touch($pth['folder']['plugins'] . 'miniblog/css/stylesheet.css');

    $text = '<?php' . "\n\n"
          . Miniblog_findConfigValue(array(
              'archive_archiving_date',
              'archive_show_archive_button;;archive_select_button',
              'archive_archive_headline_style;p',
              'posts_from_pages;true',
              'posts_from_single_files;true',
              'posts_from_members',
              'posts_authorised_members',
              'backlinks_also_below_content',
              'comments_plugin',
              'comments_default_on;true',
              'blogpage_order_posts;newest',
              'blogpage_limit_nr_of_posts_per_page',
              'blogpage_2_columns',
              'teaser_headline_style;h1;title_style',
              'teaser_headline_is_link;true',
              'teaser_img_path_for_members;;teaser_img_path_single-file_posts',
              'teaser_img_position;teaser',
              'teaser_2nd_source;<p',
              'teaser_generated_length;250',
              'category_show_category_in_teaser;true;category_show_category',
              'category_category_selection_buttons;true;category_select_button',
              'category_enable_multicategory',
              'category_from_pagenames_with;;category_from_pages_with_this_keyword',
              'date_show_date;true',
              'date_format;d.m.Y',
              'keyword_show_keyword;true',
              'keyword_2nd_source;<h6'))
          . '$plugin_cf[\'miniblog\'][\'version\']="'
          . MINIBLOG_VERSION . '";' . "\n"
          . "\n" . '?>' . "\n";

    $config = $pth['folder']['plugins'] . 'miniblog/config/config.php';

    if (!file_put_contents($config, $text)) {
        e('cntwriteto', 'file', $config);
        return false;
    } else {
      // give out notice that updating was successful
      return '<div style="display:block; width:100%; border:1px solid red;'
             . 'margin:2em 0;">'
             . '<h4 style="text-align:center; margin:0; padding:.5em;"> '
             . sprintf($plugin_tx['miniblog']['update_successful'], MINIBLOG_VERSION)
             . '</h4></div>';
    }
}

/**
 * Checks if old config value exist and creates new config values
 */
function Miniblog_findConfigValue($itemArray)
{
	global $plugin_cf;
    $o = '';

    foreach ($itemArray as $value) {
        list($item, $default, $oldname) = array_pad(explode(';',$value), 3, '');
        $name = $oldname ? $oldname : $item;
        $value = isset($plugin_cf['miniblog'][$name])
            ? $plugin_cf['miniblog'][$name]
            : (isset($plugin_cf['miniblog'][$item])
              ? $plugin_cf['miniblog'][$item]
              : $default);

        $o .= '$plugin_cf[\'miniblog\'][\'' . $item . '\']="'
                . $value . '";' . "\n";
    }
    return $o;
}