window.onload = function() {
  var SIZE = 4;
  var TILE_SIZE = 100;

  var puzzleArea = document.getElementById("puzzlearea");
  var puzzlePieces = puzzleArea.getElementsByTagName("div");

  var emptyCol;
  var emptyRow;

  startUp();
  document.getElementById("shufflebutton").onclick = shuffle;

  function startUp() {
    for (var i = 0; i < puzzlePieces.length; i++ ) {
      var piece = puzzlePieces[i];
      var number = parseInt(piece.textContent);
      var correctIndex = number - 1;
      var row = Math.floor(correctIndex / SIZE);
      var col = correctIndex % SIZE;

      piece.className = "square";
      piece.dataset.number = number;
      setPiecePosition(piece, row, col);
      setPieceBackground(piece, row, col);
      piece.onclick = handlePieceClick;

    }

    emptyRow = SIZE - 1;
    emptyCol = SIZE - 1;
    updateMovableHighlights();
  }

  function setPiecePosition(piece, row, col) {
    piece.dataset.row = row;
    piece.dataset.col = col;
    piece.style.left = (col * TILE_SIZE) + "px";
    piece.style.top = (row * TILE_SIZE) + "px";
  }

  function setPieceBackground(piece, row, col) {
    piece.style.backgroundImage = "url('background.jpg')";
    piece.style.backgroundPosition = (-col * TILE_SIZE) + "px " + (-row * TILE_SIZE) + "px";
  }
  
  function handlePieceClick() {
    if (!isAdjacentToEmpty(this)) {
      return;
    }
    movePiece(this);
    updateMovableHighlights();
  }

  function updateMovableHighlights() {
    for (var i = 0; i < puzzlePieces.length; i++) {
      var piece = puzzlePieces[i];
      if (isAdjacentToEmpty(piece)) {
        piece.classList.add("movable");
      }
      else {
        piece.classList.remove("movable");
      }
    }
  }

  function isAdjacentToEmpty(piece) {
    var row = parseInt(piece.dataset.row);
    var col = parseInt(piece.dataset.col);

    var rowDiff = Math.abs(row - emptyRow);
    var colDiff = Math.abs(col - emptyCol);

    return (rowDiff + colDiff) === 1;
  }

  function movePiece(piece) {
    var oldRow = parseInt(piece.dataset.row);
    var oldCol = parseInt(piece.dataset.col);

    setPiecePosition(piece, emptyRow, emptyCol)
    emptyRow = oldRow;
    emptyCol = oldCol;

  }

  function getMovablePieces() {
    var movable = [];
    for (var i = 0; i < puzzlePieces.length; i++) {
      if (isAdjacentToEmpty(puzzlePieces[i])) {
        movable.push(puzzlePieces[i]);
      }
    }
    return movable;
  }

  function shuffle() {
    var numMoves = 300;

    for (var i = 0; i < numMoves; i++) {
      var movable = getMovablePieces();
      var randomIndex = parseInt(Math.random() * movable.length);
      var randomPiece = movable[randomIndex];

      movePiece(randomPiece);
    }
    updateMovableHighlights();
  }

}


