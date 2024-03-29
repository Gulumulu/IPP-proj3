<?php

// holds the path to the directory with the test files
$tests_dir = null;
// holds the name of the parser file
$parse_file = null;
// holds the name of the interpret file
$int_file = null;
// states if recursive searching for test files is active
$recursive = false;
// states if the script should only test the parser or not
$parse_only = false;
// states if the script should only test the interpreter or not
$int_only = false;

$src_files = array();
$rc_files = array();
$in_files = array();
$out_files = array();
$xml_files = array();
$txt_files = array();
$created_files = array();

// create a new html document for test.php output
$html_doc = new DOMDocument();
$html_doc->preserveWhiteSpace = false;
$html_doc->formatOutput = true;

/**
 * Function checks the script input arguments and stores the necessary information
 */
function parse_arguments() {
    global $tests_dir, $recursive, $parse_file, $int_file, $parse_only, $int_only;

    // set the possible arguments
    $shortopts = "";
    $longopts = array(
        "help",
        "directory:",
        "recursive",
        "parse-script:",
        "int-script:",
        "parse-only",
        "int-only"
    );

    // gets the input arguments
    $options = getopt($shortopts, $longopts);

    // if help argument is present state the message and exit
    if (array_key_exists("help", $options)) {
        printf("This is the script for testing the parser and interpreter.\n");
        printf("It checks the output of the parser and the interpreter based on specified test files.\n");
        printf("You can use these arguments:\n");
        printf("--directory=dir         specifies in which directory the tests you want to run are, default is the directory of test.php.\n");
        printf("--recursive             allows recursive searching for the test files through the given directory.\n");
        printf("--parse-script=file     specifies the name of the parser file in php7.3, default is parse.php.\n");
        printf("--int-file=file         specifies the name of the interpret file in python3, default is interpret.py.\n");
        printf("--parse-only            if present only the parser will be tested, mutually exclusive with the --int-script argument.\n");
        printf("--int-only              if present only the interpret will be tested, mutually exclusive with the --parse-script argument.");
        exit(0);
    }

    // saving the tests directory from the arguments
    if (array_key_exists("directory", $options)) {
        $tests_dir = $options["directory"];
    }

    // if recursive argument is present the recusrive option is true
    if (array_key_exists("recursive", $options)) {
        $recursive = true;
    }

    // checks if parse-script and int-only arguments aren't present at the same time
    if (array_key_exists("parse-script", $options) and array_key_exists("int-only", $options)) {
        exit(10);
    }
    // if only parse-script argument is present set the parser name
    elseif (array_key_exists("parse-script", $options)) {
        $parse_file = $options["parse-script"];
    }
    // if it isn't present set the default name
    else {
        $parse_file = "parse.php";
    }

    // checks if int-script and parse-only arguments aren't present at the same time
    if (array_key_exists("int-script", $options) and array_key_exists("parse-only", $options)) {
        exit(10);
    }
    // if only int-script argument is present set the interpreter name
    elseif (array_key_exists("int-script", $options)) {
        $int_file = $options["int-script"];
    }
    // if it isn't present set the default name
    else {
        $int_file = "interpret.py";
    }

    if (array_key_exists("parse-only", $options) and array_key_exists("int-only", $options)) {
        exit(10);
    }
    elseif (array_key_exists("parse-only", $options)) {
        $parse_only = true;
    }
    elseif (array_key_exists("int-only", $options)) {
        $int_only = true;
    }
}

function get_files($path) {
    global $recursive, $src_files, $rc_files, $in_files, $out_files;

    $dir_content = scandir($path);

    foreach ($dir_content as $file) {
        // ingoring the . and .. folders
        if ($file === "." || $file === "..") {
            continue;
        }
        // recursively traversing all the directories
        elseif ($recursive && is_dir($path . "/" . $file)) {
            get_files($path . "/" . $file);
        }
        // if a .src file is found
        elseif (preg_match("/^.*.src$/", $file)) {
            array_push($src_files, $path . "/" . $file);
        }
        // if a .in file is found
        elseif (preg_match("/^.*.in$/", $file)) {
            array_push($in_files, $path . "/" . $file);
        }
        // if a .out file is found
        elseif (preg_match("/^.*.out$/", $file)) {
            array_push($out_files, $path . "/" . $file);
        }
        // if a .rc file is found
        elseif (preg_match("/^.*.rc$/", $file)) {
            array_push($rc_files, $path . "/" . $file);
        }
    }
}

function run_tests($html_doc, $table) {
    global $parse_file, $int_file, $parse_only, $int_only;
    global $src_files, $rc_files, $in_files, $out_files, $xml_files, $txt_files, $created_files;

    $return_code_parser = null;
    $return_code_int = null;
    $rc_content = null;
    $out_content = null;
    $out_actual = null;

    // if neither the parser nor the interpreter files exist exit
    if (!file_exists($parse_file) and !file_exists($int_file)) {
        exit(11);
    }
    // if the parser file doesn't exist
    elseif (!file_exists($parse_file)) {
        $no_parser = $html_doc->createElement('div', "Parser file not found! Proceeding anyway...");
        $html_docAttribute = $html_doc->createAttribute('style');
        $html_docAttribute->value = 'text-align: center; font-size: 30px; font-weight: bold;';
        $no_parser->appendChild($html_docAttribute);
        $html_doc->appendChild($no_parser);
        $br = $html_doc->createElement('br');
        $html_doc->appendChild($br);
    }
    // if the interpreter file doesn't exist
    elseif (!file_exists($int_file)) {
        $no_parser = $html_doc->createElement('div', "Interpreter file not found! Proceeding anyway...");
        $html_docAttribute = $html_doc->createAttribute('style');
        $html_docAttribute->value = 'text-align: center; font-size: 30px; font-weight: bold;';
        $no_parser->appendChild($html_docAttribute);
        $html_doc->appendChild($no_parser);
        $br = $html_doc->createElement('br');
        $html_doc->appendChild($br);
    }

    // create the first row of the table
    $tr = $html_doc->createElement('tr');
    $table->appendChild($tr);

    // create the first column of the table with the test file name
    $td = $html_doc->createElement('td', "Name of the test file");
    $tdAttribute = $html_doc->createAttribute('style');
    $tdAttribute->value = 'padding-right: 100px; padding-left: 100px; border: 2px solid black; font-size: 24px; font-weight: bold; padding-top: 10px; padding-bottom: 10px;';
    $td->appendChild($tdAttribute);
    $tr->appendChild($td);

    // create the second column of the table with the expected value from the .rc files
    $td = $html_doc->createElement('td', "Expected RC");
    $tdAttribute = $html_doc->createAttribute('style');
    $tdAttribute->value = 'padding-right: 10px; padding-left : 10px; border: 2px solid black; font-size: 24px; font-weight: bold; padding-top: 10px; padding-bottom: 10px;';
    $td->appendChild($tdAttribute);
    $tr->appendChild($td);

    // if there is a parse-only input argument only create the table for parsing
    if ($parse_only == true) {
        // create the third column of the table with the value got after the parser is run
        $td = $html_doc->createElement('td', "RC - PARSER");
        $tdAttribute = $html_doc->createAttribute('style');
        $tdAttribute->value = 'padding-right: 10px; padding-left: 10px; border: 2px solid black; font-size: 24px; font-weight: bold; padding-top: 10px; padding-bottom: 10px;';
        $td->appendChild($tdAttribute);
        $tr->appendChild($td);
    }
    // if there is an int-only argument only create the table for interpreting
    elseif ($int_only == true) {
        // create the third column of the table with the value got after the interpret is run
        $td = $html_doc->createElement('td', "RC - INTERPRET");
        $tdAttribute = $html_doc->createAttribute('style');
        $tdAttribute->value = 'padding-right: 10px; padding-left: 10px; border: 2px solid black; font-size: 24px; font-weight: bold; padding-top: 10px; padding-bottom: 10px;';
        $td->appendChild($tdAttribute);
        $tr->appendChild($td);

        // create the fourth column of the table with expected interpreted output from .out files
        $td = $html_doc->createElement('td', "Interpret output");
        $tdAttribute = $html_doc->createAttribute('style');
        $tdAttribute->value = 'padding-right: 10px; padding-left: 10px; border: 2px solid black; font-size: 24px; font-weight: bold; padding-top: 10px; padding-bottom: 10px;';
        $td->appendChild($tdAttribute);
        $tr->appendChild($td);

    }
    else {
        // create the third column of the table with the value got after the parser is run
        $td = $html_doc->createElement('td', "RC - PARSER");
        $tdAttribute = $html_doc->createAttribute('style');
        $tdAttribute->value = 'padding-right: 10px; padding-left: 10px; border: 2px solid black; font-size: 24px; font-weight: bold; padding-top: 10px; padding-bottom: 10px;';
        $td->appendChild($tdAttribute);
        $tr->appendChild($td);

        // create the fourth column of the table with the value got after the interpret is run
        $td = $html_doc->createElement('td', "RC - INTERPRET");
        $tdAttribute = $html_doc->createAttribute('style');
        $tdAttribute->value = 'padding-right: 10px; padding-left: 10px; border: 2px solid black; font-size: 24px; font-weight: bold; padding-top: 10px; padding-bottom: 10px;';
        $td->appendChild($tdAttribute);
        $tr->appendChild($td);

        // create the fifth column of the table with expected interpreted output from .out files
        $td = $html_doc->createElement('td', "Interpret output");
        $tdAttribute = $html_doc->createAttribute('style');
        $tdAttribute->value = 'padding-right: 10px; padding-left: 10px; border: 2px solid black; font-size: 24px; font-weight: bold; padding-top: 10px; padding-bottom: 10px;';
        $td->appendChild($tdAttribute);
        $tr->appendChild($td);
    }

    // running the parser and interpret tests
    foreach ($src_files as $file) {
        $src = explode("/", $file);

        // create a new table row for every test
        $tr = $html_doc->createElement('tr');
        $table->appendChild($tr);

        // create a table cell with the name of each test
        $td = $html_doc->createElement('td', $file);
        $tdAttribute = $html_doc->createAttribute('style');
        $tdAttribute->value = 'border: 2px solid black; padding-top: 10px; padding-bottom: 10px;';
        $td->appendChild($tdAttribute);
        $tr->appendChild($td);

        // if the corresponding .rc file exists load the value from it
        if ($rc = preg_grep("/" . str_replace(".src", ".rc", $src[count($src) - 1]) . "/", $rc_files)) {
            $rc = array_values($rc);
            $rc_content = trim(file_get_contents($rc[0], true));
            if (($key = array_search($rc[0], $rc_files)) !== false) {
                unset($rc_files[$key]);
            }
        }
        // if the corresponding .rc file doesn't exist, create it and put the value of 0 into it
        else {
            $filename = str_replace("src", "rc", $file);
            file_put_contents($filename, "0");
            $rc_content = "0";
            array_push($created_files, $filename);
        }

        // create a table cell with the expected value got from the .rc files
        $td = $html_doc->createElement('td', trim($rc_content));
        $tdAttribute = $html_doc->createAttribute('style');
        $tdAttribute->value = 'border: 2px solid black; padding-top: 10px; padding-bottom: 10px;';
        $td->appendChild($tdAttribute);
        $tr->appendChild($td);

        // if the parser should be run
        if ($parse_only or (!$int_only and !$parse_only)) {
            // if an .xml file already exists don't forward the parser output into it
            if ($xml = preg_grep("/" . str_replace(".src", ".xml", $src[count($src) - 1]) . "/", $xml_files)) {
                $xml = array_values($xml);
                exec('php ' . $parse_file . ' < ' . $file . " 2> /dev/null", $output, $return_code_parser);
            }
            // if an .xml file doesn't exist create one and forward the parser output into it
            else {
                exec('php ' . $parse_file . ' parse.php < ' . $file . " > " . str_replace(".src", ".xml", $file) . " 2> /dev/null", $parse_output, $return_code_parser);
                array_push($xml_files, str_replace(".src", ".xml", $file));
                array_push($created_files, str_replace(".src", ".xml", $file));
            }

            // if the return code of parser and the value in the .rc file match
            if ($return_code_parser == $rc_content or $return_code_parser == "0") {
                $td = $html_doc->createElement('td', $return_code_parser);
                $tdAttribute = $html_doc->createAttribute('style');
                $tdAttribute->value = 'border: 2px solid black; padding-top: 10px; padding-bottom: 10px; background-color: #5bff14;';
                $td->appendChild($tdAttribute);
                $tr->appendChild($td);
            }
            // if the values don't match
            else {
                $td = $html_doc->createElement('td', $return_code_parser);
                $tdAttribute = $html_doc->createAttribute('style');
                $tdAttribute->value = 'border: 2px solid black; padding-top: 10px; padding-bottom: 10px; background-color: #ff0900;';
                $td->appendChild($tdAttribute);
                $tr->appendChild($td);
            }
        }

        // create .in file for the corresponding .src file if it doesn't exist already
        if (!$in = preg_grep("/" . str_replace(".src", ".in", $src[count($src) - 1]) . "/", $in_files)) {
            $filename = str_replace("src", "in", $file);
            file_put_contents($filename, "");
            array_push($in_files, $filename, "");
            array_push($created_files, $filename);
        }

        // if the corresponding .out file exists load the value from it
        if ($out = preg_grep("/" . str_replace(".src", ".out", $src[count($src) - 1]) . "/", $out_files)) {
            $out = array_values($out);
            $out_content = file_get_contents($out[0], true);
            if (($key = array_search($out[0], $out_files)) !== false) {
                unset($out_files[$key]);
            }
        }
        // create .out file for the corresponding .src file if it doesn't exist already
        else {
            $filename = str_replace(".src", ".out", $file);
            file_put_contents($filename, "");
            array_push($out_files, $filename, "");
            array_push($created_files, $filename);
        }

        // create .txt file for the corresponding .src file if it doesn't exist already
        if (!$txt = preg_grep("/" . str_replace(".src", ".txt", $src[count($src) - 1]) . "/", $txt_files)) {
            $filename = str_replace(".src", ".txt", $file);
            file_put_contents($filename, "");
            array_push($txt_files, $filename, "");
            array_push($created_files, $filename);
        }

        // if both the interpret and the parser should be called use .xml files for the interpret
        if (!$int_only and !$parse_only) {
            // if an .xml file already exists forward it into the interpret source
            if ($xml = preg_grep("/" . str_replace(".src", ".xml", $src[count($src) - 1]) . "/", $xml_files)) {
                $xml = array_values($xml);
                // if an .in file already exists forward it into the interpret input
                if ($in = preg_grep("/" . str_replace(".src", ".in", $src[count($src) - 1]) . "/", $in_files)) {
                    $in = array_values($in);
                    if (($key = array_search($in[0], $in_files)) !== false) {
                        unset($in_files[$key]);
                    }
                }
                // if a .txt file already exists forward the interpret output into it
                if ($txt = preg_grep("/" . str_replace(".src", ".txt", $src[count($src) - 1]) . "/", $txt_files)) {
                    $txt = array_values($txt);
                    if (($key = array_search($txt[0], $txt_files)) !== false) {
                        unset($txt_files[$key]);
                    }
                }
                exec('python3 ' . $int_file . ' --source=' . $xml[0] . ' --input=' . $in[0] . ' > ' . $txt[0] . ' 2> /dev/null', $int_output, $return_code_int);
            }
        }
        // if only the interpret should be called use the .src files for the source
        elseif ($int_only) {
            // if an .in file already exists forward it into the interpret input
            if ($in = preg_grep("/" . str_replace(".src", ".in", $src[count($src) - 1]) . "/", $in_files)) {
                $in = array_values($in);
                if (($key = array_search($in[0], $in_files)) !== false) {
                    unset($in_files[$key]);
                }
            }
            // if a .txt file already exists forward the interpret output into it
            if ($txt = preg_grep("/" . str_replace(".src", ".txt", $src[count($src) - 1]) . "/", $txt_files)) {
                $txt = array_values($txt);
                if (($key = array_search($txt[0], $txt_files)) !== false) {
                    unset($txt_files[$key]);
                }
            }
            exec('python3 ' . $int_file . ' --source=' . $file . ' --input=' . $in[0] . ' > ' . $txt[0] . ' 2> /dev/null', $int_output, $return_code_int);
        }

        if ($int_only or (!$int_only and !$parse_only)) {
            // if the corresponding .txt file exists load the value from it
            $txt_content = file_get_contents($txt[0], true);

            // if the return code of the interpret and the value in the .rc file match
            if ($return_code_int == trim($rc_content)) {
                $td = $html_doc->createElement('td', $return_code_int);
                $tdAttribute = $html_doc->createAttribute('style');
                $tdAttribute->value = 'border: 2px solid black; padding-top: 10px; padding-bottom: 10px; background-color: #5bff14;';
                $td->appendChild($tdAttribute);
                $tr->appendChild($td);
            }
            // if the values don't match
            else {
                $td = $html_doc->createElement('td', $return_code_int);
                $tdAttribute = $html_doc->createAttribute('style');
                $tdAttribute->value = 'border: 2px solid black; padding-top: 10px; padding-bottom: 10px; background-color: #ff0900;';
                $td->appendChild($tdAttribute);
                $tr->appendChild($td);
            }

            // if the actual output matches the expected output
            if (trim($out_content) == trim($txt_content)) {
                $td = $html_doc->createElement('td', "OUTPUT IS CORRECT");
                $tdAttribute = $html_doc->createAttribute('style');
                $tdAttribute->value = 'border: 2px solid black; padding-top: 10px; padding-bottom: 10px; background-color: #5bff14;';
                $td->appendChild($tdAttribute);
                $tr->appendChild($td);
            }
            else {
                // if the output doesn't match the expecte output
                $td = $html_doc->createElement('td', "INCORRECT OUTPUT");
                $tdAttribute = $html_doc->createAttribute('style');
                $tdAttribute->value = 'border: 2px solid black; padding-top: 10px; padding-bottom: 10px; background-color: #ff0900;';
                $td->appendChild($tdAttribute);
                $tr->appendChild($td);
            }
        }
    }
}

// function to deal with the input arguments
parse_arguments();

// checking if the interpret file exists
if (!file_exists($int_file)) {
    exit(11);
}

// checking if the parser file exists
if (!file_exists($parse_file)) {
    exit(11);
}

// function to fill the arrays with specific files for testing
get_files($tests_dir);

// create the head of the html document
$head = $html_doc->createElement('head');
$title = $html_doc->createElement('title', 'Test of the parser and interpreter!');
$head->appendChild($title);

// create the header text
$header = $html_doc->createElement('div', "TEST.PHP RESULTS");
$html_docAttribute = $html_doc->createAttribute("style");
$html_docAttribute->value = "text-align: center; color: red; font-size: 50px; font-weight: bold";
$header->appendChild($html_docAttribute);
$head->appendChild($header);

// blank space to separate text in the html document
for ($i = 0; $i < 3; $i++) {
    $br = $html_doc->createElement('br');
    $head->appendChild($br);
}

// finish creating the head of the document
$html_doc->appendChild($head);

// start the body of the html document
$body = $html_doc->createElement('body');

// create the description text for the user in the html document
$text = $html_doc->createElement('div', "This is the results showcase html document for the test.php script. It tests the parser and interpreter with manually written tests. The results are shown in a table below. Red table cell signifies an error, green one signifies success.");
$html_docAttribute = $html_doc->createAttribute("style");
$html_docAttribute->value = "text-align: center; font-size: 16px;";
$text->appendChild($html_docAttribute);
$body->appendChild($text);

// blank space to separate text in the html document
for ($i = 0; $i < 2; $i++) {
    $br = $html_doc->createElement('br');
    $body->appendChild($br);
}

// create the table for displaying the results in the html document
$table = $html_doc->createElement('table');
$html_docAttribute = $html_doc->createAttribute('style');
$html_docAttribute->value = 'text-align: center; font-size: 18px; margin-left: auto; margin-right: auto; border-collapse: collapse; border: 2px solid black;';
$table->appendChild($html_docAttribute);

// run the parser and interpreter tests
run_tests($html_doc, $table);

// finish creating the html document
$table->appendChild($html_docAttribute);
$body->appendChild($table);
$html_doc->appendChild($body);

foreach ($created_files as $del) {
    unlink($del);
}

// print the html document out
print $html_doc->saveHTML();