// Database Functions page: Save, Save As, Restore and Clear.
// Everything goes through api/db_*.php. Clear and Restore need the confirmation word
// typed in, and the server checks it again.

document.addEventListener("DOMContentLoaded", function () {
    const statusEl = document.getElementById("db-status");
    const saveBtn = document.getElementById("save-btn");
    const saveAsForm = document.getElementById("saveas-form");
    const saveAsName = document.getElementById("saveas-name");
    const saveAsBtn = document.getElementById("saveas-btn");
    const replaceBox = document.getElementById("replace-box");
    const replaceMsg = document.getElementById("replace-msg");
    const replaceYes = document.getElementById("replace-yes");
    const replaceNo = document.getElementById("replace-no");
    const backupNote = document.getElementById("backup-note");
    const backupTable = document.getElementById("backup-table");
    const backupBody = document.getElementById("backup-body");
    const restoreBox = document.getElementById("restore-box");
    const restoreName = document.getElementById("restore-name");
    const restoreConfirm = document.getElementById("restore-confirm");
    const restoreGo = document.getElementById("restore-go");
    const restoreCancel = document.getElementById("restore-cancel");
    const clearConfirm = document.getElementById("clear-confirm");
    const clearBtn = document.getElementById("clear-btn");
    const noBackupRow = document.getElementById("clear-nobackup-row");
    const noBackup = document.getElementById("clear-nobackup");

    let busy = false;           // one operation at a time: no double-clicks
    let pendingName = null;     // Save As name waiting for "replace it?"
    let restoreFile = null;     // backup chosen for restore

    // ---- helpers ----------------------------------------------------------------------

    function setStatus(message, isError) {
        statusEl.textContent = message;
        statusEl.style.color = isError ? "#b00020" : "";
    }

    function setBusy(value) {
        busy = value;
        saveBtn.disabled = value;
        saveAsBtn.disabled = value;
        replaceYes.disabled = value;
        restoreGo.disabled = value || restoreConfirm.value !== "RESTORE";
        clearBtn.disabled = value || clearConfirm.value !== "CLEAR";
        backupBody.querySelectorAll("button").forEach(function (b) { b.disabled = value; });
    }

    // POST a JSON body and resolve with the parsed reply (success or not).
    function post(url, body) {
        return fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(body)
        }).then(function (response) { return response.json(); });
    }

    function countsText(counts) {
        return counts.donors + " donors, " + counts.bidders + " bidders, " +
               counts.items + " items, " + counts.payments + " payments";
    }

    function formatSize(bytes) {
        return bytes < 1024 ? bytes + " B" : (bytes / 1024).toFixed(1) + " KB";
    }

    function networkError(err) {
        console.error(err);
        setStatus("Could not reach the server -- check your connection.", true);
    }

    // Runs one operation with the buttons locked.
    function run(task) {
        if (busy) {
            return;
        }
        setBusy(true);
        task().catch(networkError).then(function () { setBusy(false); });
    }

    function hideReplace() {
        replaceBox.hidden = true;
        pendingName = null;
    }

    function hideRestore() {
        restoreBox.hidden = true;
        restoreFile = null;
        restoreConfirm.value = "";
        restoreGo.disabled = true;
    }

    // ---- the list of saved backups --------------------------------------------------------

    function loadBackups() {
        return fetch("api/db_list.php")
            .then(function (response) { return response.json(); })
            .then(function (result) {
                backupBody.textContent = "";
                if (!result.success) {
                    backupNote.textContent = "Could not load the list of backups.";
                    backupTable.hidden = true;
                    return;
                }
                if (result.backups.length === 0) {
                    backupNote.textContent = "No backups saved yet.";
                    backupTable.hidden = true;
                    return;
                }
                backupNote.textContent = "Stored in the \"" + result.folder + "\" folder of the project.";
                backupTable.hidden = false;
                result.backups.forEach(function (b) {
                    const tr = document.createElement("tr");

                    const name = document.createElement("td");
                    name.textContent = b.file;
                    tr.appendChild(name);

                    const when = document.createElement("td");
                    when.textContent = new Date(b.modified * 1000).toLocaleString();
                    tr.appendChild(when);

                    const size = document.createElement("td");
                    size.textContent = formatSize(b.size);
                    tr.appendChild(size);

                    const dl = document.createElement("td");
                    const link = document.createElement("a");
                    link.href = "api/db_download.php?file=" + encodeURIComponent(b.file);
                    link.textContent = "Download";
                    dl.appendChild(link);
                    tr.appendChild(dl);

                    const rs = document.createElement("td");
                    const btn = document.createElement("button");
                    btn.type = "button";
                    btn.textContent = "Restore...";
                    btn.addEventListener("click", function () { chooseRestore(b.file); });
                    rs.appendChild(btn);
                    tr.appendChild(rs);

                    backupBody.appendChild(tr);
                });
            })
            .catch(function (err) {
                console.error(err);
                backupNote.textContent = "Could not load the list of backups.";
            });
    }

    // ---- Save / Save As ---------------------------------------------------------------------

    saveBtn.addEventListener("click", function () {
        hideReplace();
        run(function () {
            setStatus("Saving...");
            return post("api/db_save.php", {}).then(function (result) {
                if (!result.success) {
                    setStatus(result.error, true);
                    return;
                }
                setStatus("Saved as \"" + result.file + "\" (" + countsText(result.counts) + ").");
                return loadBackups();
            });
        });
    });

    function saveAs(name, overwrite) {
        run(function () {
            setStatus("Saving...");
            return post("api/db_save.php", { name: name, overwrite: overwrite }).then(function (result) {
                if (result.success) {
                    hideReplace();
                    saveAsName.value = "";
                    setStatus("Saved as \"" + result.file + "\" (" + countsText(result.counts) + ").");
                    return loadBackups();
                }
                if (result.error_code === "FILE_EXISTS") {
                    pendingName = name;
                    replaceMsg.textContent = result.error + ". Replace it with the current data?";
                    replaceBox.hidden = false;
                    setStatus("");
                    return;
                }
                hideReplace();
                setStatus(result.error, true);
            });
        });
    }

    saveAsForm.addEventListener("submit", function (e) {
        e.preventDefault();
        const name = saveAsName.value.trim();
        if (name === "") {
            setStatus("Type a name for the backup.", true);
            return;
        }
        hideReplace();
        saveAs(name, false);
    });

    replaceYes.addEventListener("click", function () {
        if (pendingName !== null) {
            saveAs(pendingName, true);
        }
    });
    replaceNo.addEventListener("click", function () {
        hideReplace();
        setStatus("Not saved.");
    });
    // Changing the name makes the old "replace it?" question meaningless.
    saveAsName.addEventListener("input", hideReplace);

    // ---- Restore ---------------------------------------------------------------------------------

    function chooseRestore(file) {
        restoreFile = file;
        restoreName.textContent = "\"" + file + "\"";
        restoreConfirm.value = "";
        restoreGo.disabled = true;
        restoreBox.hidden = false;
        restoreConfirm.focus();
    }

    restoreConfirm.addEventListener("input", function () {
        restoreGo.disabled = busy || restoreConfirm.value !== "RESTORE";
    });
    restoreCancel.addEventListener("click", hideRestore);

    restoreGo.addEventListener("click", function () {
        if (restoreFile === null || restoreConfirm.value !== "RESTORE") {
            return;
        }
        const file = restoreFile;
        run(function () {
            setStatus("Restoring...");
            return post("api/db_restore.php", { file: file, confirm: "RESTORE" }).then(function (result) {
                if (!result.success) {
                    setStatus(result.error, true);
                    return;
                }
                hideRestore();
                setStatus("Restored \"" + result.file + "\" (" + countsText(result.restored) +
                          "). The data from before is saved as \"" + result.safety_copy + "\".");
                return loadBackups();
            });
        });
    });

    // ---- Clear -----------------------------------------------------------------------------------------

    clearConfirm.addEventListener("input", function () {
        clearBtn.disabled = busy || clearConfirm.value !== "CLEAR";
    });

    clearBtn.addEventListener("click", function () {
        if (clearConfirm.value !== "CLEAR") {
            return;
        }
        run(function () {
            setStatus("Clearing...");
            const body = { confirm: "CLEAR" };
            if (noBackup.checked) {
                body.skip_backup = true;     // only ever sent when the box is ticked
            }
            return post("api/db_clear.php", body).then(function (result) {
                if (!result.success) {
                    if (result.error_code === "BACKUP_FAILED") {
                        // Nothing was deleted. Offer the explicit way around it.
                        noBackupRow.hidden = false;
                        setStatus(result.error + " Nothing was cleared. To clear without a safety copy, " +
                                  "tick the box below and try again.", true);
                        return;
                    }
                    setStatus(result.error, true);
                    return;
                }
                clearConfirm.value = "";
                noBackup.checked = false;
                noBackupRow.hidden = true;
                if (result.safety_copy === null) {
                    setStatus("Database cleared (" + countsText(result.deleted) + " removed). " +
                              "No safety copy was saved, so this cannot be undone.");
                } else {
                    setStatus("Database cleared (" + countsText(result.deleted) + " removed). " +
                              "A copy of the old data is saved as \"" + result.safety_copy + "\".");
                }
                return loadBackups();
            });
        });
    });

    loadBackups();
});
