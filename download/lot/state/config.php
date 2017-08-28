<?php

return [
    'union' => [
        // Defaults…
        // 1 => 'Download (%{count}% downloads)',
        2 => [
            'count' => true,
            'size' => true,
            'source' => true
        ]
    ],
    // List of file extension that aren’t download-able!
    'extension_x' => 'archive,cache,data,draft,page'
];