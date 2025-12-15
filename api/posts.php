<?php
// api/posts.php
require_once 'utils.php';
cors();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List posts or get single post
    if (isset($_GET['id'])) {
        // Get single post
        // We also want to fetch user data (username)
        // Supabase join syntax: posts?select=*,profiles(username)
        $id = $_GET['id'];
        $response = supabase_request("/rest/v1/posts?id=eq.$id&select=*,profiles(username,avatar_url)");
    } else {
        // List all posts
        $response = supabase_request("/rest/v1/posts?select=*,profiles(username,avatar_url)&order=created_at.desc");
    }

    http_response_code($response['status']);
    echo json_encode($response['body']);

} elseif ($method === 'POST') {
    // Create new post
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Basic validation
    if (!isset($input['user_id']) || !isset($input['title']) || !isset($input['content'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    $data = [
        'user_id' => $input['user_id'],
        'title' => $input['title'],
        'content' => $input['content'],
        'image_url' => isset($input['image_url']) ? $input['image_url'] : null
    ];

    $response = supabase_request('/rest/v1/posts', 'POST', $data);
    http_response_code($response['status']);
    echo json_encode($response['body']);

} elseif ($method === 'PUT') {
    // Update post (Edit)
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing Post ID']);
        exit;
    }
    
    $id = $_GET['id'];
    // In a real app, verify user owns the post here using RLS or check

    $data = [];
    if (isset($input['title'])) $data['title'] = $input['title'];
    if (isset($input['content'])) $data['content'] = $input['content'];
    if (isset($input['image_url'])) $data['image_url'] = $input['image_url'];

    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'No data to update']);
        exit;
    }

    $response = supabase_request("/rest/v1/posts?id=eq.$id", 'PATCH', $data);
    http_response_code($response['status']);
    echo json_encode($response['body']);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
