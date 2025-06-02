// script.js
document.addEventListener('DOMContentLoaded', () => {
    const habitList = document.getElementById('habitList');
    const newHabitInput = document.getElementById('newHabitInput');
    const addHabitBtn = document.getElementById('addHabitBtn');

    const API_URL = 'api.php';

    // Функция для отображения привычек
    function renderHabit(habit) {
        const listItem = document.createElement('li');
        listItem.classList.add('habit-item');
        listItem.dataset.id = habit.id;
        if (habit.is_completed_today) {
            listItem.classList.add('completed');
        }

        const habitNameSpan = document.createElement('span');
        habitNameSpan.classList.add('habit-name');
        habitNameSpan.textContent = habit.name;
        habitNameSpan.addEventListener('click', () => toggleHabitStatus(habit.id, !listItem.classList.contains('completed')));

        const deleteBtn = document.createElement('button');
        deleteBtn.classList.add('delete-btn');
        deleteBtn.textContent = 'Удалить';
        deleteBtn.addEventListener('click', () => deleteHabit(habit.id));

        listItem.appendChild(habitNameSpan);
        listItem.appendChild(deleteBtn);
        habitList.appendChild(listItem);
    }

    // Загрузка привычек при загрузке страницы
    async function fetchHabits() {
        try {
            const response = await fetch(`${API_URL}?action=get_habits`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const habits = await response.json();
            habitList.innerHTML = ''; // Очищаем список перед рендерингом
            if (habits.error) {
                console.error('Error fetching habits:', habits.error);
                alert('Ошибка загрузки привычек: ' + habits.error);
                return;
            }
            habits.forEach(renderHabit);
        } catch (error) {
            console.error('Error fetching habits:', error);
            alert('Не удалось загрузить привычки. Проверьте консоль.');
        }
    }

    // Добавление новой привычки
    async function addHabit() {
        const name = newHabitInput.value.trim();
        if (!name) {
            alert('Название привычки не может быть пустым!');
            return;
        }

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add_habit', name: name })
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            if (result.success) {
                renderHabit({ id: result.id, name: result.name, is_completed_today: 0 }); // Добавляем новую привычку в список
                newHabitInput.value = ''; // Очищаем поле ввода
            } else {
                alert('Ошибка добавления привычки: ' + (result.error || 'Неизвестная ошибка'));
            }
        } catch (error) {
            console.error('Error adding habit:', error);
            alert('Не удалось добавить привычку. Проверьте консоль.');
        }
    }

    // Отметка выполнения привычки
    async function toggleHabitStatus(id, completed) {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_habit', id: id, completed: completed })
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            if (result.success) {
                const listItem = document.querySelector(`.habit-item[data-id='${id}']`);
                if (listItem) {
                    if (completed) {
                        listItem.classList.add('completed');
                    } else {
                        listItem.classList.remove('completed');
                    }
                }
            } else {
                alert('Ошибка изменения статуса: ' + (result.error || 'Неизвестная ошибка'));
            }
        } catch (error) {
            console.error('Error toggling habit:', error);
            alert('Не удалось изменить статус привычки. Проверьте консоль.');
        }
    }
    
    // Удаление привычки
    async function deleteHabit(id) {
        if (!confirm('Вы уверены, что хотите удалить эту привычку?')) {
            return;
        }
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_habit', id: id })
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            if (result.success) {
                const listItem = document.querySelector(`.habit-item[data-id='${id}']`);
                if (listItem) {
                    listItem.remove();
                }
            } else {
                alert('Ошибка удаления привычки: ' + (result.error || 'Неизвестная ошибка'));
            }
        } catch (error) {
            console.error('Error deleting habit:', error);
            alert('Не удалось удалить привычку. Проверьте консоль.');
        }
    }

    // Назначение обработчиков событий
    addHabitBtn.addEventListener('click', addHabit);
    newHabitInput.addEventListener('keypress', (event) => {
        if (event.key === 'Enter') {
            addHabit();
        }
    });

    // Первоначальная загрузка привычек
    fetchHabits();
});