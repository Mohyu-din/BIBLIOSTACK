<?php
session_start();
include 'db_connect.php'; // Connect to your database
header('Content-Type: application/json');

// --- CONFIGURATION ---
$apiKey = "YOUR_GEMINI_API_KEY_HERE"; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = $input['message'] ?? '';

    if (empty($userMessage)) {
        echo json_encode(['reply' => "I'm listening. How can I help you with Bibliostack?"]);
        exit;
    }

    // --- STEP 1: CHECK YOUR DATABASE (The "Brain") ---
    // We look for keywords in the user's message to see if they are asking about specific books.
    
    // Extract potential keywords (simple approach: split by space)
    $keywords = explode(' ', $userMessage);
    $db_context = "";
    
    // Search DB for these keywords
    $found_books = [];
    foreach ($keywords as $word) {
        if (strlen($word) > 3) { // Ignore short words like "the", "and"
            $word = mysqli_real_escape_string($conn, $word);
            $sql = "SELECT title, author, category FROM books WHERE title LIKE '%$word%' OR category LIKE '%$word%' OR author LIKE '%$word%' LIMIT 3";
            $result = $conn->query($sql);
            
            while($row = $result->fetch_assoc()) {
                $found_books[] = "- " . $row['title'] . " by " . $row['author'] . " (" . $row['category'] . ")";
            }
        }
    }

    // Deduplicate found books
    $found_books = array_unique($found_books);

    // Create the "Context String"
    if (!empty($found_books)) {
        $db_context = "Here is a list of actual books available in the Bibliostack library related to the user's query:\n" . implode("\n", $found_books) . "\n\nUse this list to recommend books to the user. If the user asks for a book not in this list, say we don't have it yet.";
    } else {
        $db_context = "I searched the Bibliostack library database but found no specific books matching the user's keywords. If they asked for a recommendation, suggest general classic literature, but mention we might not have it in our specific library yet.";
    }

    // --- STEP 2: DEFINE THE AI PERSONALITY ---
    $systemPrompt = "You are 'BiblioBot', the exclusive AI Guide for the Bibliostack website.
    
    YOUR KNOWLEDGE BASE:
    $db_context

    YOUR RULES:
    1. You are NOT Google Gemini. You are a Bibliostack employee.
    2. Your goal is to guide users to read books ON THIS WEBSITE.
    3. If the user asks 'What features do you have?', tell them: 'You can Read books, Bookmark them, Post Comments, and Save Private Notes.'
    4. If the user asks 'How do I publish?', tell them: 'Click the menu icon and select Register as Publisher.'
    5. Keep answers short, friendly, and helpful.
    ";

    // --- STEP 3: SEND TO API ---
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $apiKey;
    
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $systemPrompt . "\n\nUser Question: " . $userMessage]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        echo json_encode(['reply' => "My brain is offline briefly. (Connection Error)"]);
    } else {
        $decoded = json_decode($response, true);
        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            $botReply = $decoded['candidates'][0]['content']['parts'][0]['text'];
            
            // Clean up formatting for the chat window
            $botReply = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $botReply); // Bold
            $botReply = str_replace("* ", "<br>• ", $botReply); // Lists
            
            echo json_encode(['reply' => $botReply]);
        } else {
            echo json_encode(['reply' => "I'm thinking... but I got confused. Try asking simply?"]);
        }
    }
    curl_close($ch);
}
?>