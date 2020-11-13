<?php
/**
 * A simple php to browse a DocumentRoot directory where it list all dirs and files.
 *
 * SECURITY WARNING:
 * Exposing directory and files is consider security risk for publicly hosted server! This 
 * script is only intended for internal web site and serve as tool. 
 *
 * Project Owner: Zemian Deng
 * Project Home: https://github.com/zemian/purple-index
 * License: The MIT License (MIT)
 * 
 * Dependencies: 
 *  - Bulma CSS (for UI styling) https://bulma.io/
 *  - Cloc (for dir stats) https://github.com/AlDanial/cloc
 *
 * Release Notes: 
 * 
 * 1.0.0 2020-11-04
 *  - List files alphabetically.
 *  - Each file should be listed as a link and go to the page when clicked.
 *  - List dir separately on the side as navigation.
 *  - Each dir is a link to browse sub dir content recursively. 
 *  - Parent dir link
 * 
 * 1.1.0 2020-11-12
 *  - New style look with footer.
 *  - Navigation on links for each sub dir.
 * 
 * 1.1.1 2020-11-13
 *  - Remove unused parent parameter
 *  - Fix dir parameter to not have slash prefix
 *  - Fix error display
 * 
 * 1.2.0 2020-11-13
 *  - Add view dir stats link and output
 *  - Add AJAX to support for 'cloc' API for dir stats
 *  - Add progress bar while waiting for dir stats
 */

// Global vars
$root_path = __DIR__;

function validate_path($list_path, $browse_dir) {
    $error = null;
    // Validate Inputs
    if ( (substr_count($browse_dir, '.') > 0) /* It should not contains '.' or '..' relative paths */
        || (!is_dir($list_path)) /* It should exists. */
    ) {
        $error = "ERROR: Invalid directory.";
    }
    return $error;
}

// Process AJAX request first
if (isset($_GET['cloc'])) {
    $browse_dir = $_GET['dir'] ?? '';
    $list_path = ($browse_dir === '') ? $root_path : "$root_path/$browse_dir";

    header('Content-Type: application/json');
    $error = validate_path($list_path, $browse_dir);
    if ($error === null) {
        $output = shell_exec("cloc $list_path");
        if ($output !== null) {
            echo json_encode(array('output' => $output));
        } else {
            http_response_code(500);
            echo json_encode(array('error_message' => "Failed to execute 'cloc' command."));
        }
    } else {
        echo json_encode(array('error_message' => $error));
    }
    
    
    exit;
}

// Page vars
$title = 'Index';
$browse_dir = $_GET['dir'] ?? '';
$dirs = [];
$files = [];
$url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$dir_stat = isset($_GET['dir_stats']);
$list_path = ($browse_dir === '') ? $root_path : "$root_path/$browse_dir";
$error = validate_path($list_path, $browse_dir);

// Get files and dirs listing
if ($error === null) {
    // Build dir navigation links
    $dir_links = explode('/', $browse_dir);
    $dir_links_len = count($dir_links);
    $dir_paths = [];
    $dir_links_idx = 1;
    foreach ($dir_links as &$dir) {
        $parent_path = implode('/', $dir_paths);
        $path = "$parent_path/$dir";
        $dir_paths []= $dir;
        if ($dir_links_idx++ < $dir_links_len) { // Update all except last element
            $dir = "<a href='$url_path?dir=$path'>$dir</a>"; // Update by ref!
        }
    }
    $dir_nav_links = implode('/', $dir_links);
    
    // We need to get rid of the first two entries for "." and ".." returned by scandir().
    $list = array_slice(scandir($list_path), 2);
    foreach ($list as $item) {
        // NOTE: To avoid security risk, we always use $list_path as base path! Never go outside of it!
        if (is_dir("$list_path/$item")) {
            // We will not show hidden ".folder" folders
            if (substr_compare($item, '.', 0, 1) !== 0) {
                array_push($dirs, $item);
            }
        } else {
            array_push($files, $item);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://unpkg.com/bulma">
    <title><?php echo $title; ?></title>
    <style>
        .tag.has-purple-bg {
            background-color: hsl(294, 71%, 79%);
        }
        .progress.has-purple-bg {
            background-color: hsl(294, 71%, 79%);
        }
    </style>
</head>
<body>
<div class="section">
    <div class="level">
        <div class="level-left">
            <div class="level-item">
                <h1 class="title"><?php echo $title; ?></h1>
            </div>
        </div>
        <div class="level-right">
            <div class="level-item">
                <div class="field is-grouped">
                    <div class="control">
                        <div class="tags has-addons">
                            <span class="tag">Directories</span><span class="tag has-purple-bg"><?php echo count($dirs); ?></span>
                        </div>
                    </div>
                    <div class="control">
                        <div class="tags has-addons">
                            <span class="tag">Files</span><span class="tag has-purple-bg"><?php echo count($files); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error !== null) { ?>
        <div class="notification is-danger">
            <?php echo $error; ?>
        </div>
    <?php } else { ?>
        <div class="columns">
            <div class="column is-one-third" >
                <!-- List of Directories -->
                <div class="menu p-2" style="min-height: 60vh;"> 
                    <?php // Bulma menu-label always capitalize words, so we override it to not do that for dir name sake. ?>
                    <p class="menu-label" style="text-transform: inherit;"><a href="<?php echo $url_path; ?>">Directory:</a> <?php echo $dir_nav_links; ?></p>
                    <ul class="menu-list">
                        <?php foreach ($dirs as $item) { ?>
                        <li><a href="index.php?dir=<?php echo ($browse_dir === '') ? $item : "$browse_dir/$item"; ?>"><?php echo $item; ?></a></li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
            <div class="column">
                <?php if (count($files) === 0) { ?>
                    <p><i>No files found!</i></p>
                <?php } else { ?>
                    <!-- List of Files -->
                    <ul class="panel has-background-white">
                        <?php foreach ($files as $item) { ?>
                            <li class="panel-block"><a href="<?php echo "$browse_dir/$item"; ?>"><?php echo $item; ?></a></li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div> <!-- end of columns -->

        <?php if ($dir_stat) { ?>
            <div id='dir-stat'">
                <h1 class="subtitle has-text-centered">Directory Statistic
                    <span class="help">(provided by <a href="https://github.com/AlDanial/cloc">cloc</a>)</span>
                </h1>
                <progress id="dir-stat-progress" class="progress is-small has-purple-bg" max="100"></progress>
                <div class="level">
                    <div class="level-item">
                        <pre id="cloc-output" style="visibility: hidden;"></pre>
                    </div>
                </div>
            </div>
            <div id='dir-stat-error' style="visibility: hidden;" class="has-text-centered">
                <p>There seems to be a problem with the <code>cloc</code> command tool. 
                    Have you installed it? Try <code>brew install clock</code> if you are on a MacOSX.</p>
            </div>
        <?php } ?>
        
    <?php } ?>
</div> <!-- end of section -->

<div class="footer has-background-white">
    <div class="level">
        <div class="level-item has-text-centered">
            <div>
            <p>Powered by <a href="https://github.com/zemian/purple-index">purple-index</a></p> 
            <?php if (!$dir_stat) {
                echo "<p></p><a href='$url_path?dir=$browse_dir&dir_stats'>view dir stats</a></p>";    
            } ?>
            </div>
        </div>
    </div>
</div>

<?php if ($dir_stat) { ?>
    <script>
        var url = '<?php echo "$url_path?cloc&dir=$browse_dir" ?>';
        document.addEventListener('DOMContentLoaded', function () {
            fetch(url).then(resp => resp.json()).then(data => {
                document.getElementById('dir-stat-progress').style.visibility = 'hidden';
                if (!data.error_message) {
                    var el = document.getElementById('cloc-output');
                    el.innerText = data.output;
                    el.style.visibility = 'visible';
                } else {
                    console.warn('Fetch failed: ' + data.error_message);
                    document.getElementById('dir-stat-error').style.visibility = 'visible';
                }
            });
        });
    </script>
<?php } ?>

</body>
</html>
