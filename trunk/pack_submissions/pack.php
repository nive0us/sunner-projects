<?php  // $Id: submissions.php,v 1.43 2006/08/28 08:42:30 toyomoyo Exp $

define('RAR_PATH', '/usr/bin/rar');

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
    $assname = clean_filename($assignment->name);
    $target = "$CFG->dataroot/$course->id/packed_submissions/$assname/";
    if (!check_dir_exists($target, true, true)) {
        error("Can't mkdir ".$target);
    }

    print_box_start();

    //Check wether there are groups which has not been packed
    $groups = groups_get_all_groups($course->id);
    $packall = false;   //Pack all submissions into one archive.
    $packed = false;
    if (!$groups) {
        if (file_exists($target . $assname . '.rar')) {
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
            error("Can't mkdir $temp_dir");
        }

        //Copy submisstions to temp dir
        echo '整理文件...';
        flush();
        recurse_copy($source, $temp_dir);
        if ($dh = opendir($temp_dir)) {
            while (($file = readdir($dh)) !== false) {
                if (is_numeric($file) && is_dir($temp_dir.$file)) {
                    $user = get_record_select('user', "id = $file", 'lastname, firstname, idnumber');
                    $dest = $temp_dir . fullname($user). "[$file]";
                    if (!rename($temp_dir.$file, $dest)) {
                        error("Can't rename to ".$dest);
                    }
                    // extract teacher's comment and grade from db
                    $submission = get_record('assignment_submissions', 'assignment', $assignment->id, 'userid', $file);
                    if ($submission->timemarked != 0) {
                        $dest .= '/feedback';
                        mkdir($dest);

                        $content = "<html>\n<body>\n";
                        $content .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /></head>";
                        $teacher = get_record_select('user', "id = $submission->teacher", 'lastname, firstname');
                        $content .= fullname($teacher).'在'.userdate($submission->timemarked).'将此作业评分为 ';
                        $content .= "<strong>$submission->grade</strong>分。评注如下：</ br></ br>\n";
                        $content .= "<div>\n$submission->submissioncomment\n</div>";
                        $content .= "\n</body>\n</html>\n";

                        file_put_contents($dest.'/feedback.html', $content);
                    }
                }
            }
            closedir($dh);
        } else {
            error("Can't open $temp_dir");
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
                        $dirname = fullname($user). "[$user->id]/";
                        if (is_dir($temp_dir.$dirname))
                            $dirs[] = $dirname;
                    }
                    if (count($dirs) != 0) {
                        $command = "export LC_ALL=zh_CN.UTF-8 ; cd $temp_dir ; ".RAR_PATH." a -r $target$group->name.rar " . implode(' ', $dirs) . ' >/dev/null ' ;
                        $command = quotemeta($command);
                        system($command);
                        if (file_exists("$target$group->name.rar"))
                            echo '小组“'.$group->name.'”打包成功。<br />';
                        else
                            echo '小组“'.$group->name.'”打包失败。<br />';
                    } else {
                        echo '小组“'.$group->name.'”没有人提交作业。<br />';
                    }
                    flush();
                }
            }
        } else { //Pack all
            $command = "export LC_ALL=zh_CN.UTF-8 ; cd $temp_dir ; ".RAR_PATH." a $target$assname.rar" ;
            $command = quotemeta($command) . ' * >/dev/null';
            system($command);
            if (file_exists("$target$assname.rar"))
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

    //Copy the function from internet
    function recurse_copy($src,$dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    recurse_copy($src . '/' . $file,$dst . '/' . $file);
                }
                else {
                    copy($src . '/' . $file,$dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    } 
?>
