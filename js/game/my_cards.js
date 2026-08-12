// Expects a global `BingoCardsConfig` object to be set before this file loads:
// {
//   gameId: number,
//   drawnNumbers: number[],
//   pattern: number[][],
//   autoMode: boolean,
//   gameOverInitial: boolean
// }

(function () {
  const config = window.BingoCardsConfig || {};
  const drawnNumbers = config.drawnNumbers || [];
  const gamePattern = config.pattern || [];
  const autoMode = !!config.autoMode;
  const gameId = config.gameId;

  let gameOver = !!config.gameOverInitial;

  const lastGameId = localStorage.getItem("last_game_id");
  if (lastGameId != gameId) {
    localStorage.clear();
    localStorage.setItem("last_game_id", gameId);
  }

  async function checkGameOverOnce(previousGameOverRef) {
    try {
      const res = await fetch("functions/check_game_over.php");
      const data = await res.json();
      const isOver = data.gameOver;

      if (!previousGameOverRef.value && isOver) {
        window.gameOverShown = true;

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
    const previousGameOverRef = { value: false };
    setInterval(() => checkGameOverOnce(previousGameOverRef), 5000);

    const cells = card.querySelectorAll(".bingo-cell");
    const bingoButton = card.querySelector(".bingo-btn");

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

    // ----- Long polling for new numbers (no auto-color unless autoMode) -----
    async function pollNewNumbers(lastNumber = 0) {
      try {
        const res = await fetch(
          `functions/get_drawn_numbers.php?lastNumber=${lastNumber}`,
        );
        const data = await res.json();

        if (data.newNumbers.length > 0) {
          data.newNumbers.forEach((n) => {
            drawnNumbers.push(n);

            if (autoMode) {
              cells.forEach((cell) => {
                const number = parseInt(cell.dataset.number);
                if (number === n) {
                  cell.classList.add("marked");
                  manualMarks.add(number);
                }
              });
            }
          });

          localStorage.setItem(
            storageKey,
            JSON.stringify(Array.from(manualMarks)),
          );
          verifyBingo();
          lastNumber = Math.max(...drawnNumbers);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setTimeout(() => pollNewNumbers(lastNumber), 1000);
      }
    }

    pollNewNumbers();

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

          let data;
          try {
            data = await res.json();
          } catch (jsonErr) {
            console.error("JSON parse error:", jsonErr);
            Swal.fire({
              icon: "error",
              title: "Invalid response",
              text: await res.text(),
            });
            return;
          }

          if (data.success) {
            disableAllCards();

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
  }

  document.addEventListener("DOMContentLoaded", () => {
    document
      .querySelectorAll(".bingo-card")
      .forEach((card, cardIndex) => initCard(card, cardIndex));
  });
})();
