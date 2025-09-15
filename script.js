document.addEventListener('DOMContentLoaded', () => {

    const habitsContainer = document.getElementById('habits-container');
    const awardsContainer = document.getElementById('awards-container');
    const ecoScoreEl = document.getElementById('eco-score');
    const habitsTodayEl = document.getElementById('habits-today');
    const currentStreakEl = document.getElementById('current-streak');
    const suggestForm = document.getElementById('suggest-habit-form');
    const formFeedback = document.getElementById('form-feedback');
    const modal = document.getElementById('award-modal');
    const modalBody = document.getElementById('modal-body');
    const closeModal = document.getElementsByClassName('close-button')[0];
    
    const API_URL = 'api.php';
    let impactChart;

    async function initializeApp() {
        try {
            const [habits, awards, userData] = await Promise.all([
                fetchData('getHabits'),
                fetchData('getAwards'),
                fetchData('getUserData')
            ]);

            renderHabits(habits, userData.tracked_today);
            renderAwards(awards, userData.earned_awards);
            updateDashboard(userData);
            renderChart(userData.chart_data);

        } catch (error) {
            console.error('Initialization failed:', error);
            habitsContainer.innerHTML = '<p>Could not load habits. Please try again later.</p>';
        }
    }

    async function fetchData(action, data = null) {
        const url = `${API_URL}?action=${action}`;
        const options = {
            method: data ? 'POST' : 'GET',
            headers: {
                'Content-Type': 'application/json'
            },
        };
        if (data) {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    }

    function renderHabits(habits, trackedTodayIds) {
        habitsContainer.innerHTML = '';
        if (!habits || habits.length === 0) {
            habitsContainer.innerHTML = '<p>No habits found.</p>';
            return;
        }
        habits.forEach(habit => {
            const isCompleted = trackedTodayIds.includes(habit.id);
            const card = document.createElement('div');
            card.className = `habit-card ${isCompleted ? 'completed' : ''}`;
            card.dataset.habitId = habit.id;
            
            card.innerHTML = `
                <div class="icon">${habit.icon}</div>
                <h3>${habit.name}</h3>
                <p>${habit.description}</p>
                <button class="btn" ${isCompleted ? 'disabled' : ''}>
                    ${isCompleted ? 'Completed!' : 'Track It!'}
                </button>
            `;
            
            if (!isCompleted) {
                card.querySelector('.btn').addEventListener('click', () => trackHabit(habit.id));
            }
            
            habitsContainer.appendChild(card);
        });
    }

    function renderAwards(awards, earnedAwards) {
        awardsContainer.innerHTML = '';
        const earnedAwardIds = earnedAwards.map(a => a.award_id);

        awards.forEach(award => {
            const isEarned = earnedAwardIds.includes(award.id);
            const badge = document.createElement('div');
            badge.className = `award-badge ${isEarned ? 'earned' : ''}`;
            badge.title = `${award.name}: ${award.description}`;
            
            badge.innerHTML = `
                <div class="icon">${award.icon}</div>
                <p>${award.name}</p>
            `;
            awardsContainer.appendChild(badge);
        });
    }
    
    function updateDashboard(userData) {
        ecoScoreEl.textContent = userData.eco_score;
        habitsTodayEl.textContent = `${userData.tracked_today.length} / ${userData.total_habits}`;
        currentStreakEl.textContent = `${userData.streak} Days`;
    }

    function renderChart(chartData) {
        const ctx = document.getElementById('impactChart').getContext('2d');
        if (impactChart) {
            impactChart.destroy();
        }
        impactChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Habits Tracked',
                    data: chartData.data,
                    backgroundColor: 'rgba(46, 125, 50, 0.6)',
                    borderColor: 'rgba(46, 125, 50, 1)',
                    borderWidth: 1,
                    borderRadius: 5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    async function trackHabit(habitId) {
        try {
            const result = await fetchData('trackHabit', { habit_id: habitId });

            if (result.success) {
                const card = document.querySelector(`.habit-card[data-habit-id='${habitId}']`);
                if (card) {
                    card.classList.add('completed');
                    const btn = card.querySelector('.btn');
                    btn.textContent = 'Completed!';
                    btn.disabled = true;
                    btn.replaceWith(btn.cloneNode(true));
                }

                const userData = await fetchData('getUserData');
                updateDashboard(userData);
                renderChart(userData.chart_data);

                if (result.new_awards && result.new_awards.length > 0) {
                    const awards = await fetchData('getAwards');
                    renderAwards(awards, userData.earned_awards);
                    showAwardModal(result.new_awards[0]);
                }
            } else {
                alert('Failed to track habit. ' + result.message);
            }
        } catch (error) {
            console.error('Error tracking habit:', error);
            alert('An error occurred. Please try again.');
        }
    }
    
    async function handleSuggestionSubmit(e) {
        e.preventDefault();
        const formData = new FormData(suggestForm);
        const habitData = {
            name: formData.get('habit-name'),
            description: formData.get('habit-description')
        };
        
        if (habitData.name.length < 5 || habitData.description.length < 15) {
             formFeedback.textContent = 'Please fill out the form correctly.';
             formFeedback.style.color = 'red';
             return;
        }

        try {
            const result = await fetchData('suggestHabit', habitData);
            if(result.success) {
                formFeedback.textContent = 'Thank you for your suggestion!';
                formFeedback.style.color = 'var(--primary-color)';
                suggestForm.reset();
            } else {
                formFeedback.textContent = 'Submission failed. Please try again.';
                formFeedback.style.color = 'red';
            }
        } catch(error) {
            console.error('Error suggesting habit:', error);
            formFeedback.textContent = 'An error occurred. Please try again later.';
            formFeedback.style.color = 'red';
        }
        
        setTimeout(() => formFeedback.textContent = '', 4000);
    }

    function showAwardModal(award) {
        modalBody.innerHTML = `
            <h2>New Award Unlocked!</h2>
            <div class="award-icon">${award.icon}</div>
            <h3>${award.name}</h3>
            <p>${award.description}</p>
        `;
        modal.style.display = 'block';
    }

    closeModal.onclick = () => {
        modal.style.display = 'none';
    }
    window.onclick = (event) => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    
    suggestForm.addEventListener('submit', handleSuggestionSubmit);
    initializeApp();
});