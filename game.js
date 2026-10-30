(() => {
    const app = document.getElementById('game-app');
    if (!app) return;

    const code = app.dataset.code;
    const csrf = app.dataset.csrf;
    const status = document.getElementById('room-status');
    const roundLabel = document.getElementById('round-label');
    const timerLabel = document.getElementById('timer-label');
    const lobbyPanel = document.getElementById('lobby-panel');
    const startButton = document.getElementById('start-game');
    const questionPanel = document.getElementById('question-panel');
    const questionKicker = document.getElementById('question-kicker');
    const questionText = document.getElementById('question-text');
    const answerOptions = document.getElementById('answer-options');
    const answerFeedback = document.getElementById('answer-feedback');
    const resultsPanel = document.getElementById('results-panel');
    const winnerHeading = document.getElementById('winner-heading');
    const leaderboard = document.getElementById('leaderboard');
    const playerList = document.getElementById('player-list');
    const playerCount = document.getElementById('player-count');
    const copyButton = document.getElementById('copy-invite');
    let activePosition = null;
    let requestInFlight = false;
    let stopped = false;

    function setMessage(message, isError = false) {
        status.textContent = message;
        status.classList.toggle('error-text', isError);
    }

    function setPanel(panel) {
        lobbyPanel.hidden = panel !== 'lobby';
        questionPanel.hidden = panel !== 'playing';
        resultsPanel.hidden = panel !== 'finished';
    }

    async function request(action, extra = {}) {
        const response = await fetch('game_api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
            },
            body: JSON.stringify({ action, code, ...extra }),
        });
        let payload;
        try {
            payload = await response.json();
        } catch {
            throw new Error('The server did not return a valid game response. Check the database settings or server logs.');
        }
        if (!response.ok) {
            throw new Error(payload.error || 'The game request failed.');
        }
        return payload;
    }

    function renderPlayers(players, playing) {
        playerCount.textContent = String(players.length);
        playerList.replaceChildren();

        players.forEach((player, index) => {
            const item = document.createElement('li');
            item.className = 'player-row';

            const identity = document.createElement('span');
            identity.className = 'player-identity';
            const avatar = document.createElement('span');
            avatar.className = `player-avatar avatar-${index % 5}`;
            avatar.textContent = player.username.slice(0, 1).toUpperCase();
            const name = document.createElement('span');
            name.className = 'player-name';
            name.textContent = player.username;
            if (player.is_you) {
                const you = document.createElement('span');
                you.className = 'you-tag';
                you.textContent = 'YOU';
                name.append(' ', you);
            }
            identity.append(avatar, name);

            const detail = document.createElement('span');
            detail.className = 'player-detail';
            if (playing) {
                detail.textContent = `${player.score} pt${player.score === 1 ? '' : 's'}${player.answered ? ' · answered' : ''}`;
            } else {
                detail.textContent = `${player.score} pt${player.score === 1 ? '' : 's'}`;
            }

            item.append(identity, detail);
            playerList.append(item);
        });
    }

    function renderQuestion(game) {
        if (activePosition !== game.position) {
            activePosition = game.position;
            questionKicker.textContent = `QUESTION ${game.position + 1} OF ${game.total}`;
            questionText.textContent = game.question.question;
            answerOptions.replaceChildren();
            answerFeedback.textContent = '';

            ['a', 'b', 'c', 'd'].forEach((letter) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'game-answer';
                button.dataset.answer = letter;
                const key = document.createElement('span');
                key.className = 'answer-letter';
                key.textContent = letter.toUpperCase();
                const label = document.createElement('span');
                label.className = 'game-answer-label';
                label.textContent = game.question[`option_${letter}`];
                button.append(key, label);
                button.addEventListener('click', () => submitAnswer(game.position, letter));
                answerOptions.append(button);
            });
        }

        const seconds = Math.max(0, Number(game.time_left) || 0);
        timerLabel.textContent = `00:${String(seconds).padStart(2, '0')}`;
        timerLabel.classList.toggle('timer-low', seconds <= 5);
        answerOptions.querySelectorAll('button').forEach((button) => {
            button.disabled = game.has_answered || seconds === 0;
        });
        if (game.has_answered) {
            answerFeedback.textContent = 'Answer locked in. Waiting for the rest of the crew…';
        } else if (seconds === 0) {
            answerFeedback.textContent = 'Time is up. Moving to the next question…';
        } else {
            answerFeedback.textContent = 'Choose one answer before the timer runs out.';
        }
    }

    function renderResults(game) {
        const winners = game.winners || [];
        winnerHeading.textContent = winners.length > 1
            ? `It's a tie! ${winners.join(' and ')} share the win.`
            : `${winners[0] || 'The winner'} wins the game!`;
        leaderboard.replaceChildren();
        game.players.forEach((player, index) => {
            const item = document.createElement('li');
            item.className = 'leaderboard-row';
            const place = document.createElement('span');
            place.className = 'leaderboard-place';
            place.textContent = index === 0 ? '✦' : String(index + 1).padStart(2, '0');
            const name = document.createElement('strong');
            name.textContent = player.username;
            const score = document.createElement('span');
            score.className = 'leaderboard-score';
            score.textContent = `${player.score} / ${game.total}`;
            item.append(place, name, score);
            leaderboard.append(item);
        });
    }

    function render(game) {
        renderPlayers(game.players || [], game.status === 'playing');
        if (game.status === 'lobby') {
            activePosition = null;
            setPanel('lobby');
            roundLabel.textContent = 'Waiting';
            timerLabel.textContent = '—';
            setMessage(`${game.players.length} of 12 players in the room. Share the invite so your crew can join.`);
            startButton.hidden = !game.is_host;
            startButton.disabled = game.players.length < 2;
        } else if (game.status === 'playing') {
            setPanel('playing');
            roundLabel.textContent = `${game.position + 1} / ${game.total}`;
            const answered = game.players.filter((player) => player.answered).length;
            setMessage(`Waiting for answers · ${answered} of ${game.players.length} locked in.`);
            renderQuestion(game);
        } else {
            setPanel('finished');
            roundLabel.textContent = 'Complete';
            timerLabel.textContent = '—';
            setMessage('That’s the final score. Thanks for playing!');
            renderResults(game);
            stopped = true;
        }
    }

    async function poll() {
        if (requestInFlight || stopped) return;
        requestInFlight = true;
        try {
            render(await request('state'));
        } catch (error) {
            setMessage(error.message || 'Could not refresh the game. Trying again…', true);
        } finally {
            requestInFlight = false;
        }
    }

    async function submitAnswer(position, answer) {
        answerOptions.querySelectorAll('button').forEach((button) => {
            button.disabled = true;
        });
        answerFeedback.textContent = 'Recording your answer…';
        try {
            await request('answer', { position, answer });
            await poll();
        } catch (error) {
            answerFeedback.textContent = error.message || 'Your answer could not be recorded.';
            await poll();
        }
    }

    startButton.addEventListener('click', async () => {
        startButton.disabled = true;
        try {
            await request('start');
            await poll();
        } catch (error) {
            setMessage(error.message || 'The game could not start.', true);
            startButton.disabled = false;
        }
    });

    copyButton.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(window.location.href);
            copyButton.textContent = 'Invite link copied';
            window.setTimeout(() => {
                copyButton.textContent = 'Copy invite link';
            }, 1800);
        } catch {
            copyButton.textContent = `Room code: ${code}`;
        }
    });

    poll();
    window.setInterval(poll, 1200);
})();
