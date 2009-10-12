<?php  // $Id: submissions.php,v 1.43 2006/08/28 08:42:30 toyomoyo Exp $

define('RAR_PATH', '/usr/bin/rar');
define('UNRAR_PATH', '/usr/bin/unrar');

    require_once("../../config.php");
    require_once("../../lib/filelib.php");
    require_once("lib.php");

    $id   = optional_param('id', 0, PARAM_INT);          // Course module ID
    $a    = optional_param('a', 0, PARAM_INT);           // Assignment ID
    $mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?

    if ($id) {
        if (! $cm = get_coursemodule_from_id('assignment', $id)) {
            error("Course Module ID was incorrect");
        }

        if (! $assignment = get_record("assignment", "id", $cm->instance)) {
            error("assignment ID was incorrect");
        }

        if (! $course = get_record("course", "id", $assignment->course)) {
            error("Course is misconfigured");
        }
    } else {
        if (!$assignment = get_record("assignment", "id", $a)) {
            error("Course module is incorrect");
        }
        if (! $course = get_record("course", "id", $assignment->course)) {
            error("Course is misconfigured");
        }
        if (! $cm = get_coursemodule_from_instance("assignment", $assignment->id, $course->id)) {
            error("Course Module ID was incorrect");
        }
    }

    require_login($course->id, false, $cm);

    //header
    $strassignments = get_string('modulenameplural', 'assignment');
    $navigation = build_navigation('打包下载', $cm);
    $pagetitle = strip_tags($course->shortname.': '.$strassignments.': '.format_string($assignment->name,true).': 打包下载');
    print_header($pagetitle, $course->fullname, $navigation, "", "", true, '', navmenu($course));

    require_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $cm->id));

    //Mkdir to store packages.
    $target = "$CFG->dataroot/$course->id/packed_submissions/$assignment->name/";
    if (!check_dir_exists($target, true, true)) {
        error("Can't mkdir ".$target);
    }

    print_box_start();

    //Check wether there are groups which has not been packed
    $groups = groups_get_all_groups($course->id);
    $packall = false;   //Pack all submissions into one archive.
    $packed = false;
    if (!$groups) {
        if (file_exists($target . $assignment->name . '.rar')) {
            echo '作业已经打包过了。<br />';
            $packed = true;
        } else {
            $packall = true;
        }
    } else {
        $groups_todo = array();
        foreach ($groups as $group) {
            if (file_exists($target . $group->name . '.rar')) {   
                echo '跳过小组：'.$group->name.'<br />';
                $packed = true;
            } else {
                $groups_todo[] = $group;
            }
        }
    }

    if ($packed) {
        echo "如果想重新打包，请删除相应的rar文件。<br />";
    }

    flush();

    if (!empty($groups_todo) || $packall) {
        /// Load up the required assignment code
        require($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.'/assignment.class.php');
        $assignmentclass = 'assignment_'.$assignment->assignmenttype;
        $assignmentinstance = new $assignmentclass($cm->id, $assignment, $cm, $course);
        $source = "$CFG->dataroot/".dirname($assignmentinstance->file_area_name(0)).'/';

        // Make temp dir
        $temp_dir = $CFG->dataroot.'/temp/packass/'.$id.'/';
        fulldelete($temp_dir);
        if (!check_dir_exists($temp_dir, true, true)) {
            error("Can't mkdir ".$temp_dir);
        }

        //Copy submisstions to temp dir
        if ($files = get_directory_list($source)) {
            echo '整理文件';
            foreach ($files as $key => $file) {
                //TODO fullname as dir name
                $userid = substr($file, 0, strspn($file, '1234567890'));
                $user = get_record_select('user', "id = $userid", 'lastname, firstname, idnumber');
                $temp_dest = $temp_dir . fullname($user). "[$userid]/";
                if (!check_dir_exists($temp_dest, true, true)) {
                    error("Can't mkdir ".$temp_dest);
                }

                $path_parts = pathinfo(cleardoubleslashes($file));
                $ext= $path_parts["extension"];    //The extension of the file

                if ($ext === 'rar' && !empty($UNRAR_PATH)) {
                    $command = "export LC_ALL=$CFG->locale ; $UNRAR_PATH x $source$file $temp_dest >/dev/null";
                    system($command);
                } else if ($ext === 'zip') {
                    unzip_file($source.$file, $temp_dest, false);
                } else {
                    if (!copy($source.$file, $temp_dest.basename($file)))
                        error('Can\'t copy file');
                }

                echo '.';
                flush();
            }
        }

        //Pack now!
        echo '<br />开始打包...<br />';
        flush();
        if (!empty($groups_todo)) {
            foreach ($groups_todo as $group) {
                $dirs = array();
                $users = groups_get_members($group->id, 'u.id, u.firstname, u.lastname, u.idnumber');
                if ($users) {
                    foreach ($users as $user) {
                        $dirs[] = fullname($user). "[$user->id]/";
                    }
                    $command = "export LC_ALL=zh_CN.UTF-8 ; cd $temp_dir ; ".RAR_PATH." a $target$group->name.rar " . implode(' ', $dirs) . ' >/dev/null ' ;
                    $command = quotemeta($command);
                    system($command);
                    if (file_exists("$target$group->name.rar"))
                        echo '小组“'.$group->name.'”打包成功。<br />';
                    else
                        echo '小组“'.$group->name.'”打包失败。<br />';
                }
                flush();
            }
        } else { //Pack all
            $command = "export LC_ALL=zh_CN.UTF-8 ; cd $temp_dir ; ".RAR_PATH." a $target$assignment->name.rar" ;
            $command = quotemeta($command) . ' * >/dev/null';
            system($command);
            if (file_exists("$target$assignment->name.rar"))
                echo '作业“'.$assignment->name.'”打包成功。<br />';
            else
                echo '作业“'.$assignment->name.'”打包失败。<br />';

        }

        // Clean temp dirs and files
        if (!debugging('', DEBUG_DEVELOPER)) {
            fulldelete($temp_dir);
        }
    }
    echo '下载请到：';
    echo "<a href=\"$CFG->wwwroot/files/index.php?id=$course->id&amp;wdir=//packed_submissions\"><img src=\"$CFG->pixpath/f/folder.gif\" class=\"icon\" />&nbsp;".htmlspecialchars('packed_submissions')."</a>";

    print_box_end();
    print_footer($course);

?>
