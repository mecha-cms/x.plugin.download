<?php

$id = Path::B(__DIR__);
$state = (array) Plugin::state(__DIR__);

$test = explode('/', $url->path);
$test_file = array_pop($test);
$test_x = Path::X($test_file);
$test_token = Guardian::check(HTTP::get('token', ""), $id);

$path = implode('/', $test);

$file = File::exist(PAGE . DS . $path . DS . $test_file);

// Make sure token is valid!
if (!$test_token && $test_x && $file) {
    Message::error('download_token');
    Guardian::kick($path . HTTP::query([
        'token' => false,
        'to' => false
    ]));
// Make sure file is valid!
} else if ($test_token && $test_x && !$file) {
    Message::error('download_file');
    Guardian::kick($path . HTTP::query([
        'token' => false,
        'to' => false
    ]));
// else…
} else {
    $data = PAGE . DS . str_replace('/', DS, $path) . DS . $id . '.data';
    $counter = To::anemon(File::open($data)->read([]));
    // Internal link…
    if (
        // File does exist
        $file &&
        // And has file extension
        $test_x &&
        // And is allowed to be downloaded
        (
            empty($state['extension_x']) ||
            strpos(',' . $state['extension_x'] . ',', ',' . $test_x . ',') === false
        )
    ) {
        HTTP::header([
            'Content-Description' => 'File Transfer',
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . Path::B($file) . '"',
            'Content-Length' => filesize($file),
            'Expires' => 0,
            'Pragma' => 'public'
        ]);
        // Show the browser saving dialog!
        readfile($file);
        // Counting the downloads…
        if (isset($counter[$test_file])) {
            $counter[$test_file] = $counter[$test_file] + 1;
        } else {
            $counter[$test_file] = 1;
        }
        File::set(To::JSON($counter))->saveTo($data, 0600);
        Hook::set('on.' . $id . '.set', [
            $data,
            $data,
            [
                $test_file,
                $counter[$test_file],
                $counter
            ]
        ]);
        exit;
    // External link…
    } else if ($to = HTTP::get('to')) {
        // Counting the downloads…
        if (isset($counter[$to])) {
            $counter[$to] = $counter[$to] + 1;
        } else {
            $counter[$to] = 1;
        }
        File::set(To::JSON($counter))->saveTo($data, 0600);
        Hook::set('on.' . $id . '.set', [
            $data,
            $data,
            [
                $to,
                $counter[$to],
                $counter
            ]
        ]);
        // Redirect…
        Guardian::kick($to);
    }
}

// Parse `[[download]]` block in page content…
Block::set($id, function($content, $lot = []) use($id, $language, $state, $url) {
    return Block::replace($id, function($a, $b, $c) use($id, $language, $lot, $state, $url) {
        // Apply default text if any…
        $a = !$a && isset($state['union'][1]) ? $state['union'][1] : $a;
        // Apply default attribute(s) if any…
        $b = array_replace_recursive(isset($state['union'][2]) ? $state['union'][2] : [], $b);
        // No source path nor URL defined, abort!
        if (!isset($b['path']) && !isset($b['link'])) {
            return $c[0];
        }
        $count_s = $size_s = $source_s = "";
        $count = $size = $source = "";
        $directory = Path::F($lot['path']);
        $k = isset($b['path']) ? str_replace(DS, '/', $b['path']) : $b['link'];
        // Get download statistic from the download page…
        if (isset($b['count']) && $b['count']) {
            $i = To::anemon(File::open($directory . DS . $id . '.data')->read([]));
            $count_s = isset($i[$k]) ? $i[$k] : 0;
            $count = '<span class="' . $id . '-count">' . $count_s . '</span> ';
        }
        if (isset($b['source']) && $b['source']) {
            $source_s = Path::B($k);
            $source = ' <span class="' . $id . '-source">' . $source_s . '</span>';
        }
        if (isset($b['size']) && $b['size']) {
            // Internal source…
            if ($file = File::exist($directory . DS . $k)) {
                $size_s = File::size($file);
            // External source…
            } else if (isset($b['link'])) {
                if (is_int($b['size'])) {
                    $size_s = File::size($b['size']);
                } else if (is_string($b['size'])) {
                    $size_s = $b['size'];
                }
            }
            $size = $size_s ? ' <span class="' . $id . '-size">' . $size_s . '</span>' : "";
        }
        $label_s = $language->download;
        $label = '<span class="' . $id . '-label">' . $label_s . '</span>';
        $text = $a ? __replace__($a, [
            'count' => $count_s,
            'label' => $label_s,
            'size' => $size_s,
            'source' => $source_s
        ]) : $count . $label . $source . $size;
        $internal = isset($b['path']);
        // Remove these attribute(s)…
        unset(
            $b['count'],
            $b['path'],
            $b['size'],
            $b['source'],
            $b['link']
        );
        // Other(s) will be treated as normal HTML attribute(s)…
        $b = array_replace_recursive([
            'class[]' => [$id, $id . '--' . ($internal ? 'path' : 'link')],
            'id' => $id . ':' . md5($k),
            'title' => $count_s && $count_s > 0 ? $language->message_info_download_count($count_s) : null
        ], $b);
        $query = HTTP::query([
            'token' => Guardian::token($id),
            'to' => $internal ? false : $k
        ]);
        return HTML::a($text, $url->current . '/' . ($internal ? $k : '-link') . $query, !$internal, $b);
    }, $content);
});

// Load the asset!
Asset::set(__DIR__ . DS . 'lot' . DS . 'asset' . DS . 'css' . DS . $id . '.min.css');