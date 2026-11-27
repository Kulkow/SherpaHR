import { expect, test } from '@playwright/test';

test('participant flags a comment and owner handles it from the overview', async ({ page }) => {
  await page.goto('/');
  const sidebar = page.getByRole('navigation', { name: 'Проекты' });
  const persona = page.getByRole('combobox', { name: 'Демо-пользователь' });
  const overview = page.getByRole('region', { name: 'Задачи, требующие внимания' });
  const card = page.getByRole('complementary', { name: 'Карточка задачи' });

  await expect(overview.getByRole('button', { name: /Получить доступ к источнику/ })).toBeVisible();
  await expect(overview.getByText('Без исполнителя')).toBeVisible();
  await expect(overview.getByText('Закрыть старые согласования')).toHaveCount(0);

  await persona.selectOption('2');
  await expect(sidebar.getByRole('button', { name: 'Требуют внимания' })).toHaveCount(0);
  await sidebar.getByRole('button', { name: /Запуск клиентского портала/ }).click();
  await page.getByRole('button', { name: /Обновить приветственное письмо/ }).click();
  await card.getByRole('textbox', { name: 'Комментарий' }).fill('Анна, проверьте финальную версию.');
  await card.getByRole('checkbox', { name: 'Требует внимания руководителя' }).check();
  await card.getByRole('button', { name: 'Добавить комментарий' }).click();
  await expect(card.getByText('Анна, проверьте финальную версию.')).toBeVisible();
  await expect(card.getByRole('button', { name: 'Отметить обработанным' })).toHaveCount(0);

  await persona.selectOption('1');
  const flagged = overview.getByRole('button', { name: /Обновить приветственное письмо/ });
  await expect(flagged).toBeVisible();
  await flagged.click();
  await expect(card.getByRole('combobox', { name: 'Статус' }).locator('option')).toHaveText(['Открыта', 'Готово']);
  await card.getByRole('button', { name: 'Отметить обработанным' }).click();
  await expect(card.getByText('Отметка обработана')).toBeVisible();
  await expect(flagged).toHaveCount(0);

  await card.getByRole('button', { name: 'Закрыть карточку' }).click();
  await overview.getByRole('button', { name: /Получить доступ к источнику/ }).click();
  await card.getByRole('combobox', { name: 'Статус' }).selectOption('open');
  await card.getByRole('button', { name: 'Сохранить задачу' }).click();
  await expect(card.getByRole('combobox', { name: 'Статус' })).toHaveValue('open');
  await expect(card.getByRole('combobox', { name: 'Статус' }).locator('option')).toHaveText(['Открыта', 'В работе']);
});
