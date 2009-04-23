<?PHP //$Id: block_anti_plagiarism.php,v 1.6 2004/10/03 09:50:39 stronk7 Exp $
//require_once("../../config.php");
// This is an example of how you can construct a new block for Moodle.
// Follow the comments in the file and you 'll have your block running in no time! :)

// Wherever you see "NEWBLOCK", you will have to replace it with the filesystem name
// of the new block you are creating. This is the exact same name that will also be
// used for the directory inside blocks/ where the block file resides. For example,
// the "admin" block resides in /blocks/admin/ and is named block_admin.php.
// If you are creating a "Hello world!" block, a good candidate would be "hello_world".


// DONT's:
//  1. Don't use spaces
//  2. Don't use characters other than SMALL letters and the underscore

class block_anti_plagiarism extends block_list {

    // You HAVE to define a function with the same name as your class. This is called a constructor.
    // DO NOT modify the arguments of the constructor!
    function init () {
        global $course;

        // You need to define a human-friendly title for your block, for example "Test Block".
        // Since Moodle is internationalized, you should read this from a language file with get_string().
        // You can create your very own language file and put the strings your block uses in there,
        // Moodle will automatically know about it and use it. See the general README for information on
        // where to place this file and how to name it.

        // In this case, we assume that you have $string['blockname'] = "Test Block"; in your lang file.
        $this->title = get_string('blockname','block_anti_plagiarism');

        // You now have two choices: your block will display either pure HTML (generated by you) or a
        // list of items with optional icons next to each one. In that case, you do not need to write
        // HTML yourself; you will just create an array and Moodle will do the rest!
        //$this->content_type = BLOCK_TYPE_TEXT;

        // DO NOT CHANGE THIS LINE AT ALL!!!
        $this->course = $course;

        // You can use this so that your block can upgrade itself in the future, if there is need.
        // If you are just creating a new block, you do not need to change this value (but it is
        // considered polite to set it to YYYYMMDD00).
        $this->version = 2006091200;
    }

    // Apart from the constructor, there is only ONE function you HAVE to define, get_content().
    // Let's take a walkthrough! :)

    function get_content() {
        // We intend to use the $CFG global variable
        global $CFG;

        // This prevents your block from recalculating its content more than once before the page
        // is displayed to the user. Unless you KNOW that there is a VERY SPECIFIC reason not to do
        // that, accept the speed improvement and DO NOT TOUCH the next three lines.
        $course = get_record('course', 'id', $this->instance->pageid);
        
        if (!isteacher($course->id))
           return null;
        if($this->content !== NULL) {
            return $this->content;
        }

        // This is standard; you initialize your block's content.
        $this->content = &New stdClass;

        // Now you will create your block's content. You can include any of the Moodle libraries that
        // yoy want here using require_once() to use their services. If you need access to the course
        // data, there is the object $this->course which includes all relevant info. Below, we use it
        // to extract the course name.

        // IF your block is of type BLOCK_TYPE_LIST, you create two arrays instead. Since this particular
        // example is of BLOCK_TYPE_TEXT, the next lines are commented:

        $this->content->items = array(); // this is the text for each item
        $this->content->icons = array(); // this is the icon for each item

        // Now we are going to add one item (example copied from the "admin" block)
        if (! $assignments = get_all_instances_in_course("assignment", $course)) {
            $this->content->footer = get_string('noassignments', 'assignment');
        }
        else {
            foreach ($assignments as $assignment) {
//                if (strcmp($assignment->assignmenttype, 'uploadsingle') != 0 && strcmp($assignment->assignmenttype, 'uploadpe') != 0 && strcmp($assignment->assignmenttype, 'upload') != 0) {
//                    continue;
//                }

                if (!$assignment->visible) {
                    //Show dimmed if the mod is hidden
                    $this->content->items[] = "<a class=\"dimmed\" href=\"../blocks/moss/moss.php?id=$assignment->coursemodule\">".format_string($assignment->name,true)."</a>";
                } else {
                    //Show normal if the mod is visible
                    $this->content->items[] = "<a href=\"../blocks/moss/moss.php?id=$assignment->coursemodule\">".format_string($assignment->name,true)."</a>";
                }   
            }
        }
                    
        
        //$this->content->items[]='<a href="'.$CFG->wwwroot.'/course/student.php?id='.$this->course->id.'">Students...</a>';
        //$this->content->icons[]='<img src="'.$CFG->pixpath.'/i/users.gif" alt="" />';
        // You can add as many choices as you want with the above syntax.


        // If you like, you can specify a "footer" text that will be printed at the bottom of your block.
        // If you don't want a footer, set this variable to an empty string. DO NOT delete the line entirely!
        if (empty($this->content->footer)) {
            $this->content->footer = '<a href="http://theory.stanford.edu/~aiken/moss/" target="_blank">'.get_string('resulttitle', 'block_anti_plagiarism').'</a>';
        }
        // And that's all! :)
        return $this->content;
    }

    // Now comes the fun part!
    // You have done all you NEEDED to do; but there are things that you may do optionally, too!
    // Read the lines below to discover these additional features for Moodle blocks.

    //  1.  If you don't want your block to show a header (for example, you want something like the site
    //      summary in the front page), uncomment the following line:

    //function hide_header() {return true;}

    //  2.  If you want your block to be used ONLY in specific course formats, you can do that, too!
    //      To do this, you will modify the next function to return a value that includes those course
    //      formats in which you want the block to be available. The format is very simple, as you will see.
    //      Don't forget to uncomment the function declaration! :)

    function applicable_formats() {
        // Default case: the block can be used in all course types EXCEPT the SITE.
        // THERE IS NO TYPO HERE, if you don't know what | does, believe us! :)
        //return COURSE_FORMAT_WEEKS | COURSE_FORMAT_TOPICS | COURSE_FORMAT_SOCIAL | COURSE_FORMAT_SITE;

        // Sample case: We want our block to be available ONLY in weeks format:
        //return COURSE_FORMAT_WEEKS | COURSE_FORMAT_TOPICS | COURSE_FORMAT_SOCIAL;
        return array('course' => true, 'mod' => false, 'my' => false);
    }

    //  3.  If for some reason your block needs a specific amount of width to be readable, you can
    //      REQUEST that the course format grant you that width. Keep in mind that the course format will
    //      decide to what extent to honor your request, if at all, so there is NO guarantee that you
    //      will get the width you asked for. You might get less, or you might get even more!
    //      However, it's pretty safe to assume that "logical" values will be honored.
    //      To achieve this effect, uncomment the next function:
    //function preferred_width() {
        // Default case: the block wants to be 180 pixels wide
        //return 180;
    //}

    //  4.  It is possible that your block's behavior will be configurable, to some extent. This configuration
    //      will be available to the Moodle administrators only, from the Administration screen. An existing
    //      block that does this is online_users. You can refer to that for a real world example. But for now,
    //      if you desire configuration functionality, follow these simple steps:

    //      a. Uncomment the following function:
    function has_config() {
        return true;
    }

    //      b. You need to display your block's configuration screen, somehow. The preferred way to do this is
    //      create an .html file (most probably config.html) in your block's directory, and then uncomment the
    //      following function:
    //      Of course, there is no restriction to HOW you will display your configuration interface.
    //      The above is just a simple example.
    //      Note the use of $this->name(), which automatically takes the value with which you replaced NEWBLOCK.

    //      NOTES on the configuration screen:
    //      This will NEED to include a <form method="post" action="block.php">.
    //      Inside this form, you NEED to include an
    
    //      
    //      Other than that, you are free to do anything you wish.

    //      c. Finally, write a function that handles the submitted configuration data.
    //      This function will take ONE argument, which will be an object containing all form fields that
    //      were submitted. Use something like the example below to iterate among them and save their values
    //      someplace where you will be able to read them when get_content() is called.
    function handle_config($config) {
        foreach ($config as $name => $value) {
            set_config($name, $value);
        }
        return true;
    }

    // That's all! Copy the directory of your block (you didn't forget to rename it from NEWBLOCK, did you?)
    // into your /moodle/blocks/ directory and visit your site's administration screen to see the results! :)
}

?>
