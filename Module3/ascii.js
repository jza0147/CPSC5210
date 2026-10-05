
var frames = [];
var currentFrame = 0;
var savedText = "";
var timerID = null;
var currentDelay = 250;


function startPlay() {
   var textarea = document.getElementById("datatable");
   savedText = textarea.value;
   frames = textarea.value.split("=====\n");
   currentFrame = 0;
   textarea.value = frames[currentFrame];
   
   timerID = setInterval(function() {
    currentFrame = (currentFrame + 1) % frames.length;
    textarea.value = frames[currentFrame];
   }, currentDelay);

   document.getElementById("start").disabled = true;
   document.getElementById("stop").disabled = false;
   document.getElementById("animation").disabled = true;
}

function stopPlay() {
    clearInterval(timerID);
    timerID = null;
    document.getElementById("datatable").value = savedText;
    document.getElementById("start").disabled = false;
    document.getElementById("stop").disabled = true;
    document.getElementById("animation").disabled = false;

}

function selectAnimation() {
    var animator = document.getElementById("animation").value;
    document.getElementById("datatable").value = ANIMATIONS[animator];

}

function selectSize() {
    var howBig = document.getElementById("size").value;
    document.getElementById("datatable").style.fontSize = howBig;

}

function changeSpeed() {
    var speedy = document.getElementById("speed").checked;
    currentDelay = speedy ? 50: 250;

    if (timerID !== null) {
        clearInterval(timerID);
        timerID = setInterval(function() {
            currentFrame = (currentFrame + 1) % frames.length;
            document.getElementById("datatable").value = frames[currentFrame];
        } , currentDelay);

    }
    

}