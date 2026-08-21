<?php 
header("Content-Type: application/json");

$body = json_decode(file_get_contents("php://input"), true);

$dbHost = $body['argv']['0'] ?? "";
$dbName = $body['argv']['1'] ?? "";
$dbUser = $body['argv']['2'] ?? "";
$dbPass = $body['argv']['3'] ?? "";
$clientId = $body['argv']['4'] ?? 0;
$userId = $body['argv']['5'] ?? 0;
$apiKey = $body['argv']['6'] ?? "";
$prompt = $body['argv']['7'] ?? "Generate a dummy follow-up email";

if(!empty($dbHost) &&  !empty($dbName) && !empty($dbUser) && !empty($dbPass)){
    // DB Connection
    $conn = pg_connect("host=$dbHost dbname=$dbName user=$dbUser password=$dbPass");

    // Abort if connection failed. 
    if (!$conn) {
        echo "Database connection failed.";
        exit();
    }

    // Initial Prompt
    $initialPrompt = 'You are an expert business communication assistant specializing in creating professional follow-up emails using the D.E.A.L. construct.
        Email Structure Requirements:
        Four Main Sections (D.E.A.L.):
        I. [Company&#39;s] Goals: (Default: 5 bullet points)
        • List the prospect&#39;s stated needs, challenges, and requirements
        • Focus on business challenges, leadership needs, and strategic goals
        • Use action-oriented language
        II. [Company&#39;s] What We Respectfully Ask For: (Default: 3 bullet points)
        • Outline what the prospect has committed to do
        • Include follow-up meetings, information sharing, and review activities
        • Make commitments specific and actionable
        III. Our Responsibilities: (Default: 5 bullet points)
        • Detail your commitments and deliverables
        • Include testimonials, materials, introductions, and sessions you&#39;ll provide
        • Be specific about what you&#39;ll deliver and when
        IV. Next Steps: (Default: 3 bullet points)
        • List the mutual goals and expected results
        • Include alignment, next steps, and future engagements
        • Focus on measurable or observable outcomes
        Closing:
        • Reconfirm the next scheduled interaction
        • End with a positive, professional sign-off
        Customization Instructions:
        Bullet Point Flexibility: The user can override the default 10 bullets for any &quot;Requirements: [X]
        bullets&quot;

        Tone and Style Guidelines:
        • Write like a human in a conversational tone
        • Use sentence case (capitalize first letter of each sentence, keep everything else
        lowercase unless it&#39;s a proper noun)
        • For headings, use proper heading format (capitalize main words except small filler
        words like &quot;the,&quot; &quot;or,&quot; etc.)
        • Use simple words that a 12-year-old could understand while keeping the tone
        professional
        • Sound like a young person wrote it
        • Never use these specific words: journey, fix, twist, flip, clarity, navigate, shift
        • Don&#39;t use comparison framing like &quot;it&#39;s not this, it&#39;s that&quot; style
        • Don&#39;t use em dashes anywhere, use commas or periods instead
        • Avoid choppy sentence fragments, every sentence should be a complete thought
        • Action-focused language
        • Use numbered lists for all four main sections
        • Maintain consistent formatting
        • Include specific details when provided
        • Avoid jargon or overly complex language
        • Make it conversational and natural
        • Don&#39;t draw any separator lines in the text
        Quality Standards:
        • Each bullet point should be specific and actionable
        • Content should feel personalized to the email receiver’s situation
        • Maintain logical flow between sections
        • Ensure all commitments are realistic and achievable
        • Create accountability for both parties
        • End with clear next steps

        in you output don&#39;t list number of bullets in headings. just the headings

        Use proper formatting with line breaks <br>. 

        The closing section and the opening section (greeting line) must appear on separate lines.

        
        Output formatting rules:
        - Return valid HTML with the subject and the greeting message as well
        - Subject should be simple text, without <h2>. Add break after the subject. 
        - Add the greeting line in <p> in a new line.
        - Use <h2> for section headings
        - Use <ol> and <li> for bullet points
        - Use <strong> for emphasis
        - Do not return markdown
        - Do not wrap output in ```html
        - Output must be ready to insert into an HTML body
    '; 

    // Prompt 
    $old_prompt = array(
        
        array(
            'role' => 'user',
            "content" => [
                        ["type" => "input_text", "text" => $initialPrompt ]],
        ), 
        array(
            'role' => 'assistant',
            "content" =>[
                        ["type" => "output_text", "text" => "Hi, How can I help you?" ]] ,  
        ),
        array(
            'role' => 'user',
            "content" => [
                        ["type" => "input_text", "text" => $prompt ]],
        ), 
    );

    // Settings and data 
    $data = [
        "model" => "gpt-4.1-mini",
        "input" => $old_prompt, 
        "temperature" => 1, 
        "top_p" => 1
    ];

    // Call API 
    $ch = curl_init("https://api.openai.com/v1/responses");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
        
    // Decode results 
    $result = json_decode($response, true);
    
    // basic output
    $mainResult =  $result['output'][0]['content'][0]['text'] ?? 'No response';
    $totalUsedTokens =  $result['usage']['total_tokens'] ?? '0';
    $status =  $result['status'] ?? 'failure';
    $latency = $result['completed_at'] - $result['created_at'] ?? '123456';

    //Generate log 
    $query = "INSERT INTO external_script_logs
    (client_id, user_id, script_source, template_type, status, latency, token_counts, response)
    VALUES ($1, $2, $3, $4, $5, $6, $7, $8)";

    $params = [
        $clientId,
        $userId,
        $prompt,
        'Initial template',
        $status,
        $latency,
        $totalUsedTokens,
        $response
    ];

    $InsertQueryResult = pg_query_params($conn, $query, $params);

    if($InsertQueryResult){
        $mR = $mainResult; 

        $wordHtml = "
        <html>
        <head>
        <meta charset='UTF-8'>
        <style>
        body { font-family: Calibri, Arial; font-size: 11pt; }
        h2 { color:#2E74B5; }
        </style>
        </head>
        <body>
        $mR
        </body>
        </html>
        ";

        $folder = "../generated_docs/";
        // create folder if not exists
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        $fileName = "emailContent_" . time() . ".doc";
        $filePath = $folder . $fileName;
        file_put_contents($filePath, $wordHtml);

        echo json_encode([
            "success" => true,
            "content" => $mainResult, 
            "fileName" => 'generated_docs/'.$fileName
        ]);
        exit;
    }
    else{
        echo json_encode([
            "success" => false,
            "content" => "Error. Please try again.", 
            "fileName" => ''
        ]);
        exit;
    }

}

 