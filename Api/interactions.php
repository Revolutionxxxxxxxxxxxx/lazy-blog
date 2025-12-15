<?php
// api/interactions.php
require_once 'utils.php';
cors();

$method = $_SERVER['REQUEST_METHOD'];
$type = isset($_GET['type']) ? $_GET['type'] : ''; // 'comment' or 'like'

if ($method === 'GET') {
    // Get comments or likes for a post
    $post_id = isset($_GET['post_id']) ? $_GET['post_id'] : null;
    if (!$post_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing post_id']);
        exit;
    }

    if ($type === 'comment') {
        $response = supabase_request("/rest/v1/comments?post_id=eq.$post_id&select=*,profiles(username,avatar_url)&order=created_at.desc");
    } elseif ($type === 'like') {
        // Just get count or list
        // Supabase allows getting count via header Prefer: count=exact, usually done with HEAD or GET with range
        // For simplicity, we get all. For scalability, we should use count endpoint or rpc.
        $response = supabase_request("/rest/v1/likes?post_id=eq.$post_id&select=*");
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type']);
        exit;
    }

    http_response_code($response['status']);
    echo json_encode($response['body']);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($type === 'comment') {
        if (!isset($input['post_id']) || !isset($input['user_id']) || !isset($input['content'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
            exit;
        }
        $data = [
            'post_id' => $input['post_id'],
            'user_id' => $input['user_id'],
            'content' => $input['content']
        ];
        $response = supabase_request('/rest/v1/comments', 'POST', $data);

    } elseif ($type === 'like') {
        if (!isset($input['post_id']) || !isset($input['user_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
            exit;
        }
        $data = [
            'post_id' => $input['post_id'],
            'user_id' => $input['user_id']
        ];
        // Toggle like? For now, just insert. If it exists, Supabase might return error due to unique constraint.
        // If error 409 (Conflict), we can delete (unlike).
        $response = supabase_request('/rest/v1/likes', 'POST', $data);
        
        if ($response['status'] == 409) {
            // Unlike logic: Delete
            $pid = $input['post_id'];
            $uid = $input['user_id'];
            $response = supabase_request("/rest/v1/likes?post_id=eq.$pid&user_id=eq.$uid", 'DELETE');
            // Return a status indicating unliked
            echo json_encode(['status' => 'unliked']);
            exit;
        }

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type']);
        exit;
    }

    http_response_code($response['status']);
    echo json_encode($response['body']);
}
?>
