<?php
$filtered = array_filter($categories, function ($cat) use ($search) {
    return intval($cat['id']) == intval($search) ? true : false;
});

$match = null;

foreach ($filtered as $key => $value) {
    $match = $value;
    break;
}

return ucwords($match['category_name']);
