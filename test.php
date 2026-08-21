<!DOCTYPE html>
<html>
<head>
    <title>OpenAI API | AICollavia</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
</head>
<body>

<button id="askBtn">Ask OpenAI</button>
<div id="result"></div>
<div class="fileUrl"></div>

<script>
$("#askBtn").click(function () {

     const args = [
          "localhost",
          "Collavia",
          "postgres",
          "123",
          11,
          2,
          "[your_api_key_here]",
          "Write a follow-up email after a meeting. Company name: ABC Consulting;  Client name: John Smith;  Service discussed: leadership coaching; Meeting date: 12 March 2026. Create the email using the D.E.A.L. structure."
          ];


     $.ajax({
          url: "api/openai-test.php", // your backend endpoint
          type: "POST",
          dataType: "json",
          contentType: "application/json",  
          data: JSON.stringify({
               argv: args		   
          }),
          success: function (response) {
               // Show results
               $("#result").html(response.content);
               $(".fileUrl").html('<a href="'+response.fileName+'" target="_blank">'+response.fileName+'</a>');
               
          },
          error: function (err) {
               console.error(err);
          }
     });
});
</script>

</body>
</html>

