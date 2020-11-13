<?php
/**
 * A simple php to browse a DocumentRoot directory where it list all dirs and files.
 *
 * NOTE: 
 * Exposing directory and files is consider security risk for publicly hosted server! This 
 * script is only intended for internal web site and serve as tool. 
 *
 * Project Owner: Zemian Deng
 * Project Home: https://github.com/zemian/index-listing
 * Release: 
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
 * 1.2.0 SNAPSHOT
 *  - Add AJAX to support 'cloc' program summary
 */

// Process AJAX request first
if (isset($_GET['cloc'])) {
    $dir = __DIR__;
    $output = shell_exec("cloc $dir");
    
    header('Content-Type: application/json');
    if ($output !== null) {
        echo json_encode(array('output' => $output));
    } else {
        http_response_code(500);
        echo json_encode(array('error_message' => "Failed to execute 'cloc' command."));
    }
    exit;
}

// Page vars
$title = 'Index Listing';
$browse_dir = $_GET['dir'] ?? '';
$dir_stat = !isset($_GET['no_dir_stat']);
$error = '';
$dirs = [];
$files = [];
$url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Internal vars
$root_path = __DIR__;
$list_path = ($browse_dir === '') ? $root_path : "$root_path/$browse_dir";

// Validate Inputs
if ( (substr_count($browse_dir, '.') > 0) /* It should not contains '.' or '..' relative paths */
    || (!is_dir($list_path)) /* It should exists. */
) {
    $error = "ERROR: Invalid directory.";
}

// Get files and dirs listing
if (!$error) {
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
                <div class="field is-grouped is-grouped-multiline">
                    <div class="control">
                        <div class="tags has-addons">
                            <span class="tag">Directories</span><span class="tag is-primary"><?php echo count($dirs); ?></span>
                        </div>
                    </div>
                    <div class="control">
                        <div class="tags has-addons">
                            <span class="tag">Files</span><span class="tag is-primary"><?php echo count($files); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error) { ?>
        <div class="notification is-danger">
            <?php echo $error; ?>
        </div>
    <?php } else { ?>
        <div class="columns has-background-light">
            <div class="column is-one-third" style="min-height: 60vh;">
                <!-- List of Directories -->
                <div class="menu">       
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
            <div id='dir-stat' style="display: none">
                <h1 class="subtitle has-text-centered">Directory <a href="https://github.com/AlDanial/cloc">Stat</a></h1>
                <div class="level">
                    <div class="level-item">
                        <pre id="cloc-output"></pre>
                    </div>
                </div>
            </div>
            <div id='dir-stat-not-available' style="display: none" class="has-text-centered">
                <p>Want some directory statistic report?
                    Try <code>brew install <a href="https://github.com/AlDanial/cloc">cloc</a></code> 
                    if you are on a MacOSX.</p>
            </div>
        <?php } ?>
        
    <?php } ?>
</div> <!-- end of section -->

<div class="footer has-background-white">
    <div class="level">
        <div class="level-item">
            Powered by <a href="https://github.com/zemian/index-listing">index-listing</a>
        </div>
    </div>
</div>

<?php if ($dir_stat) { ?>
    <script>
        var url = '<?php $url_path ?>?cloc';
        document.addEventListener('DOMContentLoaded', function () {
            fetch(url).then(resp => resp.json()).then(data => {
                if (!data.error_message) {
                    document.getElementById('cloc-output').innerText = data.output;
                    document.getElementById('dir-stat').style.display = 'inherit';
                } else {
                    console.warn('Fetch failed: ' + data.error_message);
                    document.getElementById('dir-stat-not-available').style.display = 'inherit';
                }
            });
        });
    </script>
<?php } ?>

</body>
</html>
