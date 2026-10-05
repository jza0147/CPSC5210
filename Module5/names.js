window.onload = function() {
    new Ajax.Request(
        "https://webhome.auburn.edu/~tzt0062/babynames/babynames.php?type=list",
        {
            method: "get",
            onSuccess: namesListCompleted,
            onFailure: ajaxFailed,
            onException: ajaxFailed
        }
    );

    Event.observe($("search"), "click", searchClicked);
};

function namesListCompleted(ajax) {
    var rawNames = ajax.responseText.split("\n");

    var names = [];
    for (var i = 0; i < rawNames.length; i++) {
        var trimmedName = rawNames[i].trim();
        if (trimmedName.length > 0) {
            names.push(trimmedName);
        }
    }

    names.sort(function(a, b) {
        var lowerA = a.toLowerCase();
        var lowerB = b.toLowerCase();
        if (lowerA < lowerB) {
            return -1;
        }
        if (lowerA > lowerB) {
            return 1;
        }
        return 0;
    });

    var select = $("allnames");
    for (var j = 0; j < names.length; j++) {
        var option = document.createElement("option");
        option.value = names[j];
        option.textContent = names[j];
        select.appendChild(option);
    }

    select.disabled = false;
    $("loadingnames").hide();
}

function searchClicked() {
    var select = $("allnames");
    var name = select.value;

    // Do nothing if the blank "(choose a name)" option is selected
    if (name === "") {
        return;
    }

    var gender = $("genderm").checked ? "m" : "f";

    // Clear out any data from a previous search
    $("meaning").innerHTML = "";
    $("graph").innerHTML = "";
    $("celebs").innerHTML = "";
    $("errors").innerHTML = "";
    $("norankdata").hide();

    // Show the loading indicators for the three sections we're about to fetch
    $("loadingmeaning").show();
    $("loadinggraph").show();
    $("loadingcelebs").show();

    // Reveal the results area
    $("resultsarea").show();

    // Kick off the three data fetches
    getMeaning(name);
    getRank(name, gender);
    getCelebs(name, gender);
}

function getMeaning(name) {
    new Ajax.Request(
        "https://webhome.auburn.edu/~tzt0062/babynames/babynames.php",
        {
            method: "get",
            parameters: {
                type: "meaning",
                name: name
            },
            onSuccess: meaningCompleted,
            onFailure: ajaxFailed,
            onException: ajaxFailed
        }
    );
}

function meaningCompleted(ajax) {
    $("meaning").innerHTML = ajax.responseText;
    $("loadingmeaning").hide();
}

function getRank(name, gender) {
    new Ajax.Request(
        "https://webhome.auburn.edu/~tzt0062/babynames/babynames.php",
        {
            method: "get",
            parameters: {
                type: "rank",
                name: name,
                gender: gender

            },
            onSuccess: rankCompleted,
            onFailure: rankFailed,
            onException: ajaxFailed
        }
    );
}

function rankCompleted(ajax) {

    var ranks = ajax.responseXML.getElementsByTagName("rank");

    

    var headerRow = document.createElement("tr");
    var dataRow = document.createElement("tr");

    for (var i = 0; i < ranks.length; i++) {
        var year = ranks[i].getAttribute("year");
        var rankValue = parseInt(ranks[i].firstChild.nodeValue);

        var th = document.createElement("th");
        th.textContent = year;
        headerRow.appendChild(th);

        var td = document.createElement("td");
        var bar = document.createElement("div");
        bar.className = "rankbar";

        var height;
        if (rankValue === 0) {
            height = 0;
        } else {
            height = parseInt((1000 - rankValue) / 4);
        }
        bar.style.height = height + "px";

        var label = document.createElement("span");
        label.textContent = rankValue;
        if (rankValue >= 1 && rankValue <= 10) {
            label.className = "toprank";
        }
        bar.appendChild(label);

        td.appendChild(bar);
        dataRow.appendChild(td);
    }

    var table = $("graph");
    table.appendChild(headerRow);
    table.appendChild(dataRow);

    $("loadinggraph").hide();
}
function rankFailed(ajax, exception) {
    if (ajax.status == 404 || ajax.status == 410) {
        $("norankdata").show();
        $("loadinggraph").hide();
    } else {
        ajaxFailed(ajax, exception);
    }
}

function getCelebs(name, gender) {
    new Ajax.Request(
        "https://webhome.auburn.edu/~tzt0062/babynames/babynames.php",
        {
            method: "get",
            parameters: {
                type: "celebs",
                name: name,
                gender: gender

            },
            onSuccess: celebCompleted,
            onFailure: ajaxFailed,
            onException: ajaxFailed
        }
    );
}

function celebCompleted(ajax) {
    var data = JSON.parse(ajax.responseText);
    var actors = data.actors;

    var list = $("celebs");

    for (var i = 0; i < actors.length; i++) {
        var actor = actors[i];
        var li = document.createElement("li");

        li.textContent = actor.firstName + " " + actor.lastName + " (" + actor.filmCount + " films)";
        list.appendChild(li);
    }
    $("loadingcelebs").hide();
}



function ajaxFailed(ajax, exception) {
    var msg = "Error making Ajax request: ";
    if (exception) {
        msg += " Exception: " + exception.message;
    } else {
        msg += "Server status: " + ajax.status +
               " Status text: " + ajax.statusText +
               " Server response text: " + ajax.responseText;
    }
    $("errors").innerHTML = msg;

    $("loadingnames").hide();
    $("loadingmeaning").hide();
    $("loadinggraph").hide();
    $("loadingcelebs").hide();
}