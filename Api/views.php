<?php
// api/views.php
require_once 'utils.php';
cors();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['post_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing post_id']);
        exit;
    }
    
    $post_id = $input['post_id'];
    
    // We need to increment the view count.
    // Supabase RPC is best for atomic increment.
    // But since I cannot create RPC function easily from here without SQL access (I provided schema but user might not have run it), 
    // I will try to read, then update. THIS IS NOT ATOMIC and has race conditions, but suffices for "Lazy Blog".
    
    // Better approach: User RPC. I'll document it in schema.sql but for now implement the read-update loop as fallback.
    // Actually, I can add the RPC to schema.sql now.
    
    /* 
       To be added to schema:
       create or replace function increment_views(row_id uuid)
       returns void as $$
         update posts set views_count = views_count + 1 where id = row_id;
       $$ language sql;
    */
    
    // Attempt to call RPC
    $response = supabase_request('/rest/v1/rpc/increment_views', 'POST', ['row_id' => $post_id]);
    
    if ($response['status'] >= 400) {
       // Fallback: Read and Update (bad practice but works for MVP if RPC missing)
       $get = supabase_request("/rest/v1/posts?id=eq.$post_id&select=views_count");
       if (isset($get['body'][0]['views_count'])) {
           $new_count = $get['body'][0]['views_count'] + 1;
           $update = supabase_request("/rest/v1/posts?id=eq.$post_id", 'PATCH', ['views_count' => $new_count]);
           echo json_encode(['status' => 'updated_manually', 'count' => $new_count]);
       } else {
           http_response_code(404);
           echo json_encode(['error' => 'Post not found']);
       }
    } else {
        echo json_encode(['status' => 'incremented']);
    }

} else {
    http_response_code(405);
}
?>
