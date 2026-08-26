// Expects a global `BingoCardsConfig` object to be set before this file loads:
// {
//   gameId: number,
//   drawnNumbers: number[],
//   pattern: number[][],
//   autoMode: boolean,
//   gameOverInitial: boolean,
//   inCardChangeWindow: boolean,
//   cardChangeDeadline: string | null   // ISO timestamp
// }

(function () {
  const config = window.BingoCardsConfig || {};
  const drawnNumbers = config.drawnNumbers || [];
  const gamePattern = config.pattern || [];
  const autoMode = !!config.autoMode;
  const gameId = config.gameId;
  const letters = ["B", "I", "N", "G", "O"];

  let gameOver = !!config.gameOverInitial;

  function vibrate(pattern) {
    const prefersReducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    ).matches;
    if (navigator.vibrate && !prefersReducedMotion) {
      navigator.vibrate(pattern);
    }
  }

  const lastGameId = localStorage.getItem("last_game_id");
  if (lastGameId != gameId) {
    localStorage.clear();
    localStorage.setItem("last_game_id", gameId);
  }

  // Registries so a single polling loop can update every card on the page.
  const cardUpdateHandlers = [];
  const previousGameOverRef = { value: false };

  async function checkGameOverOnce() {
    try {
      const res = await fetch("functions/check_game_over.php");
      const data = await res.json();
      const isOver = data.gameOver;

      if (!previousGameOverRef.value && isOver) {
        window.gameOverShown = true;
        vibrate(80);

        document.querySelectorAll(".bingo-cell").forEach((cell) => {
          cell.style.pointerEvents = "none";
          cell.classList.add("opacity-50");
        });

        document.querySelectorAll(".bingo-btn").forEach((btn) => {
          btn.disabled = true;
          btn.classList.remove("bounce-btn");
        });

        Swal.fire({
          icon: "info",
          title: "Game Over!",
          text: "All winners have already been claimed.",
          confirmButtonColor: "#764ba2",
          confirmButtonText: "Back to Main Menu",
        }).then(() => {
          window.location.href = "index.php";
        });
      }

      previousGameOverRef.value = isOver;
    } catch (err) {
      console.error(err);
    }
  }

  function disableAllCards() {
    document.querySelectorAll(".bingo-cell").forEach((cell) => {
      cell.style.pointerEvents = "none";
      cell.classList.add("opacity-50");
    });

    document.querySelectorAll(".bingo-btn").forEach((btn) => {
      btn.disabled = true;
      btn.classList.remove("bounce-btn");
    });
  }

  function initCard(card, cardIndex) {
    const cells = card.querySelectorAll(".bingo-cell");
    const bingoButton = card.querySelector(".bingo-btn");
    const changeCardButton = card.querySelector(".change-card-btn");

    const storageKey = `bingo_marks_game_${gameId}_card_${cardIndex}`;
    const savedMarks = JSON.parse(localStorage.getItem(storageKey)) || [];
    const manualMarks = new Set(savedMarks);

    function verifyBingo() {
      let patternMarked = true;

      for (let row = 0; row < 5; row++) {
        for (let col = 0; col < 5; col++) {
          if (gamePattern[row][col] === 1) {
            if (row === 2 && col === 2) continue; // FREE

            const cell = Array.from(cells).find(
              (c) =>
                parseInt(c.dataset.row) === row &&
                parseInt(c.dataset.colIndex) === col,
            );

            const number = parseInt(cell?.dataset.number ?? -1);

            if (
              !cell ||
              (!cell.classList.contains("marked") &&
                (!autoMode || !drawnNumbers.includes(number)))
            ) {
              patternMarked = false;
              break;
            }
          }
        }
        if (!patternMarked) break;
      }

      if (patternMarked) {
        bingoButton.classList.remove("d-none");
        bingoButton.classList.add("bounce-btn");
      } else {
        bingoButton.classList.add("d-none");
        bingoButton.classList.remove("bounce-btn");
      }
    }

    function restoreMarks() {
      cells.forEach((cell) => {
        const number = parseInt(cell.dataset.number);
        if (manualMarks.has(number)) {
          cell.classList.add("marked");
        } else {
          cell.classList.remove("marked");
        }
      });
      verifyBingo();
    }

    cells.forEach((cell) => {
      cell.addEventListener("click", () => {
        const number = parseInt(cell.dataset.number);

        if (!drawnNumbers.includes(number)) {
          vibrate([15, 60, 15]); // quick buzz-pause-buzz = "no"
          Swal.fire({
            icon: "error",
            title: "Not Drawn!",
            text: "You cannot mark this number yet.",
            timer: 1200,
            showConfirmButton: false,
          });
          return;
        }

        cell.classList.toggle("marked");
        vibrate(15); // light tick

        if (cell.classList.contains("marked")) manualMarks.add(number);
        else manualMarks.delete(number);

        localStorage.setItem(
          storageKey,
          JSON.stringify(Array.from(manualMarks)),
        );
        verifyBingo();
      });
    });

    restoreMarks();

    // Called by the single shared poller whenever new numbers come in.
    function onNewNumbers(newNumbers) {
      if (autoMode) {
        let didMark = false;
        newNumbers.forEach((n) => {
          cells.forEach((cell) => {
            const number = parseInt(cell.dataset.number);
            if (number === n) {
              cell.classList.add("marked");
              manualMarks.add(number);
              didMark = true;
            }
          });
        });

        if (didMark) vibrate(30);

        localStorage.setItem(
          storageKey,
          JSON.stringify(Array.from(manualMarks)),
        );
      }
      verifyBingo();
    }

    cardUpdateHandlers.push(onNewNumbers);

    // ----- Handle Bingo button click -----
    if (bingoButton) {
      bingoButton.addEventListener("click", async () => {
        const markedNumbers = Array.from(cells)
          .filter((cell) => cell.classList.contains("marked"))
          .map((cell) => parseInt(cell.dataset.number));

        try {
          const res = await fetch("functions/claim_bingo.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              cardIndex: cardIndex,
              markedNumbers: markedNumbers,
            }),
          });

          const resText = await res.text();
          let data;
          try {
            data = JSON.parse(resText);
          } catch (jsonErr) {
            console.error("JSON parse error:", jsonErr);
            Swal.fire({
              icon: "error",
              title: "Invalid response",
              text: resText,
            });
            return;
          }

          if (data.success) {
            disableAllCards();
            vibrate([60, 40, 60, 40, 120]); // win pattern

            // 🔊 Play win sound
            const winSound = new Audio("js/audio/bingo_win.mp3");
            winSound.volume = 0.8; // adjust to taste
            winSound
              .play()
              .catch((err) => console.warn("Audio play blocked:", err));

            const duration = 2000;
            const end = Date.now() + duration;
            (function frame() {
              confetti({
                particleCount: 2,
                spread: 200,
                origin: { x: Math.random(), y: 0 },
              });
              if (Date.now() < end) requestAnimationFrame(frame);
            })();

            Swal.fire({
              icon: "success",
              title: "Bingo claimed!",
              text: data.message || "",
            });

            bingoButton.disabled = true;
          } else {
            vibrate(200); // flat "denied" buzz
            let errorText = data.message || "Cannot claim bingo now.";
            if (data.error) {
              errorText += "\n\n" + JSON.stringify(data.error, null, 2);
            }

            Swal.fire({
              icon: "error",
              title: "Oops!",
              html: `<pre style="text-align:left;white-space:pre-wrap;">${errorText}</pre>`,
            });
          }
        } catch (err) {
          console.error(err);
          Swal.fire({
            icon: "error",
            title: "Fetch Error",
            text: err.message || "Something went wrong while claiming bingo.",
          });
        }
      });
    }

    // ----- Handle Change Card button click -----
    // Only the neutral numbers change server-side; pattern cells (and
    // FREE) are left alone. We still clear all marks here since the
    // numbers under them are no longer guaranteed to match.
    if (changeCardButton) {
      changeCardButton.addEventListener("click", async () => {
        changeCardButton.disabled = true;

        try {
          const res = await fetch("functions/change_card.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ cardIndex: cardIndex }),
          });

          const resText = await res.text();
          let data;
          try {
            data = JSON.parse(resText);
          } catch (jsonErr) {
            console.error("JSON parse error:", jsonErr);
            Swal.fire({
              icon: "error",
              title: "Invalid response",
              text: resText,
            });
            return;
          }

          if (data.success) {
            vibrate(30);

            cells.forEach((cell) => {
              const row = parseInt(cell.dataset.row);
              const colIndex = parseInt(cell.dataset.colIndex);
              const letter = letters[colIndex];
              const newNumber = data.cardData[letter][row];

              cell.dataset.number = newNumber;
              cell.textContent = newNumber;
              cell.classList.remove("marked");
            });

            manualMarks.clear();
            localStorage.setItem(storageKey, JSON.stringify([]));
            verifyBingo();

            // One change per card — remove the button so it can't be
            // clicked again without a reload (server enforces this too).
            changeCardButton.remove();

            Swal.fire({
              icon: "success",
              title: "Card changed!",
              timer: 1000,
              showConfirmButton: false,
            });
          } else {
            vibrate(200);
            Swal.fire({
              icon: "error",
              title: "Can't change card",
              text: data.message || "Card changes are no longer allowed.",
            });
          }
        } catch (err) {
          console.error(err);
          Swal.fire({
            icon: "error",
            title: "Fetch Error",
            text:
              err.message || "Something went wrong while changing your card.",
          });
        } finally {
          changeCardButton.disabled = false;
        }
      });
    }
  }

  // ----- Card-change window countdown -----
  // Reloads the page once the window closes so PHP re-renders without
  // the Change Card button / banner (same pattern as the rest of this
  // app's live-screen polling-and-reload approach).
  function startCardChangeCountdown() {
    if (!config.inCardChangeWindow || !config.cardChangeDeadline) return;

    const wrap = document.getElementById("card-change-countdown-wrap");
    const el = document.getElementById("card-change-countdown");
    if (!wrap || !el) return;

    const deadline = new Date(config.cardChangeDeadline).getTime();

    function tick() {
      const remaining = deadline - Date.now();

      if (remaining <= 0) {
        window.location.reload();
        return;
      }

      const totalSeconds = Math.floor(remaining / 1000);
      const minutes = Math.floor(totalSeconds / 60);
      const seconds = totalSeconds % 60;
      el.textContent = `${minutes}:${String(seconds).padStart(2, "0")}`;
    }

    tick();
    setInterval(tick, 1000);
  }

  // ----- Single shared long-poll loop for the whole page (not per card) -----
  // Running one instance per card was the source of the duplicate-number bug:
  // each card's own loop started at lastNumber=0 and pushed the same numbers
  // into the shared drawnNumbers array independently.
  let pollLastNumber = drawnNumbers.length > 0 ? Math.max(...drawnNumbers) : 0;

  async function pollNewNumbers() {
    try {
      const res = await fetch(
        `functions/get_drawn_numbers.php?lastNumber=${pollLastNumber}`,
      );
      const data = await res.json();

      if (data.newNumbers.length > 0) {
        // Guard against duplicates even if the backend ever returns a
        // number that's already in drawnNumbers (e.g. due to a retry).
        const trulyNew = data.newNumbers.filter(
          (n) => !drawnNumbers.includes(n),
        );

        if (trulyNew.length > 0) {
          trulyNew.forEach((n) => drawnNumbers.push(n));
          cardUpdateHandlers.forEach((handler) => handler(trulyNew));
        }

        pollLastNumber = Math.max(...drawnNumbers);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setTimeout(pollNewNumbers, 1000);
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document
      .querySelectorAll(".bingo-card")
      .forEach((card, cardIndex) => initCard(card, cardIndex));

    // Start the shared loops exactly once per page load.
    pollNewNumbers();
    setInterval(checkGameOverOnce, 5000);
    startCardChangeCountdown();
  });
})();
