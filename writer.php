<?php
/**
 * Miniblog_XH Interface to create single file blog entries
 *
 * last change: 20.08.2016 08:52:34
 *
 */


/**
 * Prevent direct access.
 */
if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}



/**
 * displays the interface for the members to write blog postings
 */
function miniblogwriter()
{
    global $plugin_cf, $plugin_tx, $tx, $pth, $bjs, $s;
    $o = $post = $user = '';
    $ptx = $plugin_tx['miniblog'];

    // Warning in case java script is disabled
    $o .= '<noscript><div class="xh_warning" style="position:relative;">'
        . $ptx['error_javascript_missing']
        . '<div style="position:absolute;left:0;top:3em;width:100%;height:11em;background:#fff;">'
        . '</div></div></noscript>';

    // read the data file of the blog postings
    if (isset($_POST['mblognewdata'])) Miniblog_receivePostData();
    $posts = json_decode(file_get_contents($pth['folder']['content'].'miniblog/miniblog.php'), true);
    $posts = Miniblog_includeOrphans($posts);

    // check if extedit is there
    if (!function_exists ('extedit')) return '<h4>'.$tx['heading']['error'].'</h4><p>'.$ptx['error_no_extedit'].'</p>';

    // proceed only for logged in users
    if (isset($_SESSION['username'])) {

        $user = $_SESSION['username'];

        if ($plugin_cf['miniblog']['posts_authorised_members']) {
            $authorised = explode(',',$plugin_cf['miniblog']['posts_authorised_members']);
            if (!in_array($user,$authorised)) return $plugin_tx['miniblog']['error_not_authorised'];
        }

        $name = isset($_SESSION['fullname'])? $_SESSION['fullname'] : $user;


        if (!isset($posts['folder'][$user])) {
            $o .= '<h1>' . $ptx['text_welcome'] . '</h1>';
            $o .= "\n" . sprintf($ptx['text_foldernaming_request'], $name);

            $o .= '<form method="post"><label>' . $ptx['text_name_of_my_folder'] . '</label> '
                . '<input type="text" name="mblognewfolder">'
                . '<input type="hidden" name="mblogfolderuser" value="' . $user . '">'
                . '<button name="mblognewdata" type="submit"'
. ' onClick="
var folder = document.getElementsByName(\'mblognewfolder\')[0].value;
if (/\W/g.test(folder)) {
    alert(\'' . $ptx['error_allowed_chars1'] . '\');
    return false;
}">'
                . $ptx['text_send'] . '</button>'
                . '</form>' ;

        } else {
            $folder = $posts['folder'][$user];

            // find the active post
            // start with looking into $_POST
            if (isset($_POST['mblogpostname'])) {
                // in case of delete there is no active post
                if($_POST['mblogpostname'] == 'del') {
                    $post = '';
                } else {
                // if a post was added, this should be the active one
                    $post = $_POST['addpost']
                        ? $_POST['addpost']
                        // if neither add nor delete, the active one should be in $_POST
                        : ($_POST['mblogpostname'] != 'add'
                        ? $_POST['mblogpostname']
                        : '');
                    // write the found active post into a cookie
                    if ($post) setcookie ('mblogpostname', $post);
                }
            }
            // if $_POST doesn't give the active post, look into $_COOKIE
            if (!$post && isset($_COOKIE['mblogpostname'])) $post = $_COOKIE['mblogpostname'];
            // However, wenn $_COOKIE gives the post which was deleted, no active post should be set
            // and the cookie should be deleted
            if (isset($_POST['mblogdelpost']) && $_POST['mblogdelpost']
                && isset($_COOKIE['mblogpostname'])
                && $_COOKIE['mblogpostname'] == $_POST['mblogdelpost']) {
                $post = '';
                setcookie ('mblogpostname', "", time() - 3600);
            }

            // Give the user the possibility to select the post he wants to work upon
            $o .= '<form method="post">';
            $o .= $post
                ? '<p>' . $ptx['text_explain_blog_writing'] . '</p>'
                : '<p>' . $ptx['text_continue_editing_blog_post'] . '</p>';
            $o .= '<select name="mblogpostname" class="mblog_input" OnChange="
                    if(this.options[this.selectedIndex].value == \'add\' ) {
                        document.getElementById(\'newfile\').style.display = \'inline\';
                        document.getElementById(\'delfile\').style.display = \'none\';
                    } else if (this.options[this.selectedIndex].value == \'del\' ) {
                        document.getElementById(\'delfile\').style.display = \'inline\';
                        document.getElementById(\'newfile\').style.display = \'none\';
                    } else {
                        document.getElementById(\'newfile\').style.display = \'none\';
                        document.getElementById(\'delfile\').style.display = \'none\';
                        this.form.submit();}
                   ">';
            // In case there isn't any active post, start with proposal to make a choice
            if (!$post) {
                $o .= "\n" . '<option>' . $ptx['text_please_choose'] . '</option>';
            }

            $i = 0;
            if (array_key_exists($folder,$posts)) {
                foreach ($posts[$folder] as $key => $value) {
                    if (!$i && $key == $post) {
                        $selected = ' selected';
                        $i++;
                    } else $selected = '';
                    $comment = (isset($posts[$folder][$key]['publish'])
                                && $posts[$folder][$key]['publish'] == 'true')
                             ? '  &nbsp;(' . $ptx['text_publ'] . ')'
                             : '';
                    $o .= "\n" . '<option' . $selected . ' value="' . $key . '">'
                        . $key . $comment . '</option>';
                }
            }

            // new posts are not yet in the data file therefore they are being added now to the selection
            if($post && !$i) {
                $o .= "\n" . '<option value="' . $post . '" selected>' . $post . '</option>';
            }
            $o .= "\n" . '<option value="add">' . $ptx['text_add_new_post'] . '</option>';
            $o .= "\n" . '<option value="del">' . $ptx['text_delete_post'] . '</option>';
            $o .= "\n" . '</select>';
            $o .= '<span id="newfile" style="display:none;"><label> '
                . $ptx['text_short_blog_title'] . '</label> '
                . "\n" . '<input type="text" name="addpost" value="">';

            $o .= '<input type="submit" value="' . $tx['action']['ok'] . '" '
                . 'onClick="var post = document.getElementsByName(\'addpost\')[0].value;'
                . 'if (/\W/g.test(post)) {'
                . 'alert(\''
                . $ptx['text_short_blog_title'] . ': ' . $ptx['error_allowed_chars2']
                . '\');'
                . 'return false;}">';
            $o .= '</span>';
            $o .= '<span id="delfile" style="display:none;"><label> '
                . $ptx['text_name_of_delete'] . '</label> '
                . "\n" . '<input type="hidden" name="mbloguser" value="' . $user . '">'
                . "\n" . '<input type="hidden" name="mblogfolder" value="' . $folder . '">'
                . "\n" . '<input type="text" name="mblogdelpost" value="">';

            $o .= '<input type="submit" name="mblognewdata" value="' . $tx['action']['ok'] . '">';
            $o .= '</span>';
            $o .= "\n" . '</form>';


            if ($post) $o .= Miniblog_viewEditHeader($folder, $name, $post, $posts);

            $o .= $post
                ? '<span id="warning"></span><b>'
                . $ptx['text_blog_post_content'] . '</b> '
                . extedit($user, $folder . '---' . $post)
                : '';
        }
    } else {
        $o .= '<p>' . $ptx['text_login_required'] . '</p>';
    }

    return "\n" .  $o . "\n" ;
}

/**
 * Displays the header of a post, switchable to edit mode
 */
function Miniblog_viewEditHeader($folder = '', $name = '', $post, $posts, $admin = '')
{
    global $plugin_tx, $plugin_cf;
    $ptx = $plugin_tx['miniblog'];

    $o = '';
    $date    = isset($posts[$folder][$post]['date'])    ? $posts[$folder][$post]['date']     : '';
    $title   = isset($posts[$folder][$post]['title'])   ? $posts[$folder][$post]['title']    : '';
    $cat     = isset($posts[$folder][$post]['cat'])     ? $posts[$folder][$post]['cat']      : '';
    $img     = isset($posts[$folder][$post]['img'])     ? $posts[$folder][$post]['img']      : '';
    $publish = isset($posts[$folder][$post]['publish']) ? $posts[$folder][$post]['publish']  : '';
    $comments= isset($posts[$folder][$post]['comments'])? $posts[$folder][$post]['comments'] : '';
    $teaser  = isset($posts[$folder][$post]['teaser'])  ? $posts[$folder][$post]['teaser']   : '';
    $archive = isset($posts[$folder][$post]['archive']) ? $posts[$folder][$post]['archive']  : '';
    $blog    = isset($posts[$folder][$post]['blog'])    ? $posts[$folder][$post]['blog']     : '';
    $style   = isset($posts[$folder][$post]['style'])   ? $posts[$folder][$post]['style']    : '';

    $publ = $publish == 'true'
        ? ' class="mblogPublOn">' . $ptx['text_published']
        : ' class="mblogPublOff">' . $ptx['text_not_published'];
    $o .= '<p class="mblog_headEditStart">' . $ptx['text_header_blogpost'] . ' <u>'
        . $ptx['single-file_blogname_in_url'] . '/' . $folder . '/' . $post
        . '</u> <span id="mblogPublStatus"' . $publ . '</span> <span id="mblogEditAnswer"></span></p>';

    $o .= $archive
        ? '<p style="margin:0;">'
        . sprintf($ptx['text_warning_archive'],$plugin_cf['miniblog']['archive_archiving_date'])
        . '</p>'
        : '';

    $o .= '<div id="editMblogHeader" style="display:none;">';
    $o .= Miniblog_editHeader($folder, $name, $blog, $post, $publish, $comments,
             $date, $cat, $title, $teaser, $img, $style, $admin);
    $o .= '</div>';

    $o .= '<div id="viewMblogHeader" style="display:block;" class="mblog_postHeader">';
    $o .= Miniblog_Header($cat, $date, $name, $title, '', '', '', '', true);
    $o .= '</div>';


    // start dialog for displaying or editing blog header
    $o .= "\n" . '<form  style="display:inline-block;" method="post">'
        . '<button type="button" OnClick="
if(document.getElementById(\'editMblogHeader\').style.display == \'none\') {
    document.getElementById(\'viewMblogHeader\').style.display = \'none\';
    document.getElementById(\'editMblogHeader\').style.display = \'block\';
    this.innerHTML = \'' . $ptx['text_save_header'] . '\';
    document.getElementById(\'warning\').innerHTML = \'('
    . $ptx['text_warning_save_header'] . ') \';
} else {
    document.getElementById(\'viewMblogHeader\').style.display = \'block\';
    document.getElementById(\'editMblogHeader\').style.display = \'none\';
    mblogSendHeader ();
    this.innerHTML = \'' . $ptx['text_edit_header'] . '\';
    document.getElementById(\'warning\').innerHTML = \'\';
}
">'
     . $ptx['text_edit_header'] . '</button></form> ';

    return $o;
}


/**
 * Catches external files which may have been forgotten in the mblog data file
 */
function Miniblog_includeOrphans($posts)
{
    global $pth;

    $files = scandir($pth['folder']['content'] . 'extedit');
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && strpos($file, '---') !== false) {
            list($folder, $post) = array_pad(explode('---',$file), -2,'');
            $post = substr($post,0,-4);
            if ($folder && !isset($posts[$folder][$post])) {
                $posts[$folder][$post] = array();
            }
        }
    }
    return $posts;
}


/**
 * Receives the ajax request, starts the connected function and returns the functions answer
 */
if (isset($_GET['mblogwriter_ajax'])) {
    header('Content-Type: text/plain');
    echo Miniblog_receivePostData();
    exit;
}


/**
 * displays the header of a members posting as editable ajax fields
 */
function Miniblog_editHeader($folder, $name, $blog, $post, $publish, $comments,
        $date, $cat, $title, $teaser, $img, $style, $admin = '')
{
    global $pth, $plugin_cf, $plugin_tx, $bjs, $sn;
    $o = '';
    $pcf = $plugin_cf['miniblog'];
    $ptx = $plugin_tx['miniblog'];

    $nameajax = $admin
        ? '"mblogname=" + document.getElementsByName("mblogname")[0].value'
        : '"mblogname=" + "'. $name . '"';

    include 'ajax.php';

    // this div is only to get styling right in overstyled templates
    $o .= "\n" . '<div id="mblog_posting">' . "\n" ;

    If (!$admin) $o .= "\n" . '<input type="hidden" name="mblogname"  value="'. $name  . '">';
    $o .=  "\n" . '<input type="hidden" name="mblogfolder" value="'. $folder . '">'
       .  "\n" . '<input type="hidden" name="mblogpost"  value="'. $post  . '">';

    // publish blog post?
    $publishchecked = $publish && $publish != 'false' ? ' checked' : '';
    $o .= "\n"
       .  '<input type="checkbox" name="mblogpublish" value="true" '
       .  $publishchecked . '> <label>'
       .  $ptx['text_publish_post'] . '</label>';

    // allow comments?
    $commentschecked = $pcf['comments_default_on']
        ? ($comments != 'false' ? ' checked' : '')
        : ($comments && $comments != 'false' ? ' checked' : '');
    $o .= $plugin_cf['miniblog']['comments_plugin']
       ?  "\n"
       .  ' &nbsp; <input type="checkbox" name="mblogcomments" value="true" '
       .  $commentschecked . '> <label>'
       .  $ptx['text_allow_comments'] . '</label>'
       : '<input type="hidden" name="mblogcomments" value="' . $comments . '">';

    $o .=  '<br>';

    // author's name or the keyword of the blog post
    if ($admin) {
        $o .= "\n" 
           .  '<input type="text" name="mblogname" class="mblog_input" value="'
           .  $name
           .  '"> <label>' . $plugin_tx['miniblog']['text_author_or_keyword'] . '</label><br></span>';
    }

    // date of the blog post
    $dateDisplay = $pcf['date_show_date'] || $pcf['archive_archiving_date'] ? '' : 'style="display:none;"';
    $text = $date > 1 ? date($pcf['date_format'], (int)$date) : '';
     $o .= "\n" . '<span id="dateDisplay" '.$dateDisplay.'>'
       .  '<input type="text" name="mblogdate" class="mblog_input" value="'
       .  $text
       .  '"> <label>' . $ptx['text_date_of_post'] . '</label> '
       .  '<button type="button" onClick="
var d = document.getElementsByName(\'mblogdate\')[0].value;
var date = new Date();
today = (\'0\' + date.getDate()).slice(-2) + \'.\' + (\'0\' + (date.getMonth() + 1)).slice(-2) + \'.\' + date.getFullYear();
document.getElementsByName(\'mblogdate\')[0].value = today;">'
       . $plugin_tx['miniblog']['text_today'] . '</button><br></span>';

    // Select a blog in case of multiple blogs on the site
    if (strpos($ptx['single-file_backlinks_to'], ',') || $admin && $ptx['single-file_archive_page']) {
        $o .= "\n" . '<select name="mblogblog"  class="mblog_input">';
        if (strpos($ptx['single-file_backlinks_to'], ',')) {
            $blogs = explode(',', $ptx['single-file_backlinks_to']);
            if ($ptx['single-file_archive_page']) $blogs[] = $ptx['single-file_archive_page'];
            $o .= "\n" . '<option value="">' . $ptx['text_please_choose'] . '</option>';
            foreach ($blogs as $key=>$value) {
                $selected = $value == $blog ? ' selected' : '';
                $o .= "\n" . '<option' . $selected . ' value="' . $value . '">' . $value . '</option>';
            }
        } else {
                $o .= "\n" . '<option value="">' . $ptx['text_active_blog'] . '</option>';
                $selected = $blog == $ptx['single-file_archive_page']? ' selected' : '';
                $o .= "\n" . '<option' . $selected . ' value="' . $blog . '">' . $ptx['text_archive'] . '</option>';
        }
        $o .= "\n" . '</select> <label>' . $ptx['text_choose_blog'] . '</label><br>';
    } else {
        $o .= '<input type="hidden" name="mblogblog" value="' . $blog . '">';
    }


    // Select a category for the blog post
    if ($ptx['single-file_category_list']) {
        $cats = explode(',',$ptx['single-file_category_list']);
        $thiscats = explode(' ',$cat);
        $multiple = $pcf['category_enable_multicategory'] ? ' multiple' : '';
        $o .= "\n" . '<select id="mblogcat"  class="mblog_input"' . $multiple . '>';
        $o .= "\n" . '<option value="">' . $ptx['text_please_choose'] . '</option>';
        foreach ($cats as $key=>$value) {
            $value = str_replace(' ', '__', $value);
            $selected = in_array($value,$thiscats)? ' selected' : '';
            $o .= "\n" . '<option' . $selected . ' value="' . $value . '">'
                . str_replace('__', ' ', $value) . '</option>';
        }
        $o .= "\n" . '</select> <label>' . $ptx['text_category'];
        if ($multiple) $o .= ' (' . $ptx['text_usage_multicategory'] . ')';
        $o .=  '</label><br>';
    } elseif ($pcf['category_show_category_in_teaser'] || $pcf['category_category_selection_buttons']) {
        $o .= "\n" . '<input type="hidden" id="mblogcat"><p class="xh_warning">'
            . $ptx['text_enter_category_list'] . '</p>';
    } else {
        $o .= "\n" . '<input type="hidden" id="mblogcat">';
    }


    // Title of the blog post
    $o .= ' <label>' . $ptx['text_title_editing'] . '</label><br>'
       .  "\n" . '<input type="text" class="mblog_titleInput" name="mblogtitle" value="'
       .  $title . '"><br>';

    // Teaser for the blog post, teaser with expanding text area.
    $o .= "\n"
       .  '<label>' . $ptx['text_teaser_editing'] . '</label><br>'
       .  '<div class="mblog_expandingArea" ><pre><span></span>' . '<br></pre>'
       .  '<textarea name="mblogteaser">' . $teaser . '</textarea></div>'
       .  "\n";

    // Select an Image for the teaser
    if (!$admin) {
        if ($pcf['teaser_img_path_for_members']) {
            $o .= "\n" . '<select name="mblogimg"  class="mblog_input">';
            $o .= "\n" . '<option value="">' . $ptx['text_no_teaser_img'] . '</option>';
            $images = scandir($pth['folder']['images'] . $pcf['teaser_img_path_for_members']);
            $i = 0;
            foreach ($images as $image) {
                if ($image != '.' && $image != '..') {
                    if ($image == basename($img)) {
                        $selected = ' selected';
                        $i++;
                    } else $selected = '';
                    $o .= "\n" . '<option' . $selected . ' value="'
                        . rtrim($pcf['teaser_img_path_for_members']) . '/'
                        . $image . '">' . $image . '</option>';
                }
            }
            if ($i == 0 && $img) $o .= "\n" . '<option value="' . $img . '" selected>' . basename($img) . '</option>';
            $o .= "\n" . '</select> <label>' . $ptx['text_teaser_img'] . '</label><br>';
        } else {
            $o .= "\n" . '<input type="hidden" name="mblogimg" value="'
               .  $img . '">';
        }
        $o .= '<input type="hidden" name="mblogstyle" value="'
               .  $style . '">';
    } else {
        $o .= '<div style="width:100%;display:table;">';
        $o .= "\n" . '<span style="display:table-cell;width:100%;">'
            . '<input type="text" style="border:none;width:100%;text-align:right;" name="mblogimg" value="'
            .  $img . '"></span>';
        $o .= '<span style="display:table-cell">'
            . '<button type="button" style="white-space:nowrap;" onClick="filebrowser(\'image\');">'
            .  $ptx['text_image_browser'] . '</button></span>';
        $o .= '<span style="display:table-cell">';
        $o .=  "\n" . '<select name="mblogstyle" class="mblog_input">'
            . "\n" . '<option value="">' . $ptx['text_as_config'] . '</option>';
        foreach (array(
            'top' => $ptx['text_image_top'],
            'left' => $ptx['text_image_left'],
            'title' => $ptx['text_image_title'],
            'teaser' => $ptx['text_image_teaser'],
            'wide_top' => $ptx['text_image_top'] . '+' . $ptx['text_wide'],
            'wide_title' => $ptx['text_image_title'] . '+' . $ptx['text_wide'],
            'wide_teaser' => $ptx['text_image_teaser'] . '+' . $ptx['text_wide']
            ) as $key => $value) {
            $selected = $style == $key ? ' selected' : '';
            $o .= "\n" . '<option' . $selected . ' value="'
               . $key . '">'. $value . '</option>';
        }
        $o .= "\n" . '</select></span></div>';

    }

    $o .= "\n" . '</div>';

    return $o;
}


/**
 * enters new values into the data base of member's postings, also deletes posts
 */
function Miniblog_receivePostData()
{
    global $pth, $e, $plugin_cf, $plugin_tx;
    $o = '';
    $pcf = $plugin_cf['miniblog'];
    $ptx = $plugin_tx['miniblog'];

    $posts = json_decode(file_get_contents($pth['folder']['content'].'miniblog/miniblog.php'), true);


    $name        =isset($_POST['mblogname'])       ? htmlspecialchars($_POST['mblogname'])       : '';
    $newfolder   =isset($_POST['mblognewfolder'])  ? $_POST['mblognewfolder']                    : '';
    $delfolder   =isset($_POST['mblogdelfolder_x'])? $_POST['mblogdelfolder_x']                  : '';
    $folderuser  =isset($_POST['mblogfolderuser']) ? htmlspecialchars($_POST['mblogfolderuser']) : '';
    $folder      =isset($_POST['mblogfolder'])     ? htmlspecialchars($_POST['mblogfolder'])     : '';
    $blog        =isset($_POST['mblogblog'])       ? $_POST['mblogblog']                         : '';
    $post        =isset($_POST['mblogpost'])       ? htmlspecialchars($_POST['mblogpost'])       : '';
    $date        =isset($_POST['mblogdate'])       ? $_POST['mblogdate']                         : '';
    $title       =isset($_POST['mblogtitle'])      ? htmlspecialchars($_POST['mblogtitle'])      : '';
    $cat         =isset($_POST['mblogcat'])        ? $_POST['mblogcat']                          : '';
    $publish     =isset($_POST['mblogpublish'])    ? $_POST['mblogpublish']                      : false;
    $comments    =isset($_POST['mblogcomments'])   ? $_POST['mblogcomments']                     : false;
    $archive     =isset($_POST['mblogarchive'])    ? $_POST['mblogarchive']                      : false;
    $teaser      =isset($_POST['mblogteaser'])     ? htmlspecialchars($_POST['mblogteaser'])     : '';
    $img         =isset($_POST['mblogimg'])        ? $_POST['mblogimg']                          : '';
    $style       =isset($_POST['mblogstyle'])      ? $_POST['mblogstyle']                        : '';
    $del         =isset($_POST['mblogdelpost'])    ? $_POST['mblogdelpost']                      : '';


    if ($folder && $del) {
        unset($posts[$folder][$del]);
        if (is_file($pth['folder']['content'] . 'extedit/' . $folder . '---' . $del . '.htm')) {
            unlink($pth['folder']['content'] . 'extedit/' . $folder . '---' . $del . '.htm');
        }  
    }

    if ($post) {

        // check if actually a posting file exists
        if ($publish == 'true' && !is_file($pth['folder']['content'] . 'extedit/' . $folder . '---' . $post . '.htm')) {
            $o .= '<span class="mblog_error">' . $ptx['error_save_before_publishing'] . '</span>';
        }
        if ($publish == 'true' && !$title) {
            $o .= '<span class="mblog_error">' . $plugin_tx['miniblog']['error_publishing_needs_title'] . '</span>';
        }
        if ($date && strtotime($date) > 1 ) {
            $date = strtotime($date);
        } else {
            $date = '';
        }
        if ($publish == 'true' && !$date &&
            ($pcf['blogpage_order_posts'] == 'newest' || $pcf['blogpage_order_posts'] == 'oldest')) {
            $o .= '<span class="mblog_error">' . $ptx['error_publishing_needs_date'] . '</span>';
        }

        if ($pcf['archive_archiving_date'] &&
            strtotime($plugin_cf['miniblog']['archive_archiving_date']) > $date) $archive = true;

        $posts[$folder][$post]['blog']     = $blog;
        $posts[$folder][$post]['date']     = $date;
        $posts[$folder][$post]['name']     = $name;
        $posts[$folder][$post]['title']    = $title;
        $posts[$folder][$post]['cat']      = $cat;
        $posts[$folder][$post]['publish']  = $publish;
        $posts[$folder][$post]['comments'] = $comments;
        $posts[$folder][$post]['archive']  = $archive;
        $posts[$folder][$post]['teaser']   = $teaser;
        $posts[$folder][$post]['img']      = $img;
        $posts[$folder][$post]['style']    = $style;
    }

    if ($folderuser & $newfolder) {
        if ($delfolder) {
            unset($posts['folder'][$folderuser]);
        } else {
            if (preg_match('/^[A-Z0-9-_]+$/i', $newfolder)) {
                // in case no change was detected
                if (isset($posts['folder'][$folderuser]) && $posts['folder'][$folderuser] == $newfolder) return false;
                // in case the folder is already in use
                if (in_array($newfolder, $posts['folder'])) {
                    $e .= $ptx['error_folder_already_in_use'];
                    return false;
                }
                // new folder gets registered
                $posts['folder'][$folderuser] = $newfolder;
            } else {
                // illegal chars used
                $e .= $ptx['text_short_blog_title'] . ': ' . $ptx['error_allowed_chars2'];
                return false;
            }
        }
    } 

    // saving the changed file
    $jsonposts = json_encode($posts);
    if (file_put_contents($pth['folder']['content'].'miniblog/miniblog.php',
        $jsonposts, LOCK_EX) === FALSE) {
        e('cntsave', 'file', $pth['folder']['content'].'miniblog/miniblog.php');
        return 'ERROR';
    } else return $o ?  $o  : '<span class="mblog_ok">' . $ptx['text_saved']  . '</span>' ;
}
?>