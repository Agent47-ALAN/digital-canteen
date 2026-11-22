-- Digital Canteen: optional sample menu data
-- Import AFTER database/canteen.sql, from the phpMyAdmin Import tab.
--
-- Safe to import more than once:
--   * Nothing is dropped, deleted, or overwritten.
--   * A category is added only if no category with that name exists.
--   * A food item is added only if its category already has an item with that name.
--   * Items you edited, deactivated, or re-priced are left exactly as they are.

SET NAMES utf8mb4;
USE digital_canteen;

-- Categories

INSERT INTO categories (name, description, sort_order)
SELECT 'Breakfast', 'Morning favourites served until late morning', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Breakfast');

INSERT INTO categories (name, description, sort_order)
SELECT 'Meals', 'Full lunch plates and thalis', 2
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Meals');

INSERT INTO categories (name, description, sort_order)
SELECT 'Snacks', 'Quick bites for between lectures', 3
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Snacks');

INSERT INTO categories (name, description, sort_order)
SELECT 'Beverages', 'Hot and cold drinks', 4
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Beverages');

INSERT INTO categories (name, description, sort_order)
SELECT 'Sweets', 'Desserts and sweet treats', 5
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Sweets');

-- Food items (prices are in rupees)

-- Breakfast
INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Idli Sambar', 'Three steamed idlis with sambar and coconut chutney', 40.00
FROM categories c
WHERE c.name = 'Breakfast'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Idli Sambar');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Medu Vada', 'Two crisp lentil vadas with sambar and chutney', 45.00
FROM categories c
WHERE c.name = 'Breakfast'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Medu Vada');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Poha', 'Flattened rice with peanuts, onion and lemon', 30.00
FROM categories c
WHERE c.name = 'Breakfast'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Poha');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Upma', 'Semolina cooked with vegetables and curry leaves', 30.00
FROM categories c
WHERE c.name = 'Breakfast'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Upma');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Masala Dosa', 'Crisp dosa with spiced potato filling and chutney', 60.00
FROM categories c
WHERE c.name = 'Breakfast'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Masala Dosa');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Aloo Paratha', 'Stuffed flatbread served with curd and pickle', 55.00
FROM categories c
WHERE c.name = 'Breakfast'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Aloo Paratha');

-- Meals
INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Veg Thali', 'Two sabzis, dal, rice, chapati, salad and papad', 90.00
FROM categories c
WHERE c.name = 'Meals'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Veg Thali');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Dal Rice', 'Yellow dal tadka with steamed rice and pickle', 60.00
FROM categories c
WHERE c.name = 'Meals'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Dal Rice');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Rajma Chawal', 'Kidney bean curry with steamed rice', 70.00
FROM categories c
WHERE c.name = 'Meals'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Rajma Chawal');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Chole Bhature', 'Spiced chickpeas with two fried bhature', 75.00
FROM categories c
WHERE c.name = 'Meals'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Chole Bhature');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Veg Pulao', 'Fragrant rice with mixed vegetables and raita', 65.00
FROM categories c
WHERE c.name = 'Meals'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Veg Pulao');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Paneer Butter Masala Meal', 'Paneer in creamy tomato gravy with two chapatis and rice', 110.00
FROM categories c
WHERE c.name = 'Meals'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Paneer Butter Masala Meal');

-- Snacks
INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Samosa', 'Two crisp potato samosas with green chutney', 25.00
FROM categories c
WHERE c.name = 'Snacks'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Samosa');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Vada Pav', 'Spiced potato fritter in a soft bun', 20.00
FROM categories c
WHERE c.name = 'Snacks'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Vada Pav');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Veg Sandwich', 'Grilled sandwich with vegetables and cheese', 50.00
FROM categories c
WHERE c.name = 'Snacks'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Veg Sandwich');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Pav Bhaji', 'Buttery mashed vegetable curry with two pav', 70.00
FROM categories c
WHERE c.name = 'Snacks'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Pav Bhaji');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'French Fries', 'Salted potato fries with ketchup', 50.00
FROM categories c
WHERE c.name = 'Snacks'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'French Fries');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Veg Maggi', 'Instant noodles cooked with vegetables', 40.00
FROM categories c
WHERE c.name = 'Snacks'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Veg Maggi');

-- Beverages
INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Masala Chai', 'Hot milk tea with ginger and cardamom', 12.00
FROM categories c
WHERE c.name = 'Beverages'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Masala Chai');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Filter Coffee', 'Hot South Indian style coffee', 20.00
FROM categories c
WHERE c.name = 'Beverages'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Filter Coffee');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Cold Coffee', 'Chilled coffee blended with milk and ice', 50.00
FROM categories c
WHERE c.name = 'Beverages'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Cold Coffee');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Fresh Lime Soda', 'Lime with soda, sweet or salted', 30.00
FROM categories c
WHERE c.name = 'Beverages'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Fresh Lime Soda');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Buttermilk', 'Spiced chilled buttermilk', 20.00
FROM categories c
WHERE c.name = 'Beverages'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Buttermilk');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Mango Lassi', 'Thick sweet yogurt drink with mango', 45.00
FROM categories c
WHERE c.name = 'Beverages'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Mango Lassi');

-- Sweets
INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Gulab Jamun', 'Two soft milk dumplings in sugar syrup', 35.00
FROM categories c
WHERE c.name = 'Sweets'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Gulab Jamun');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Jalebi', 'Crisp spirals soaked in saffron syrup', 30.00
FROM categories c
WHERE c.name = 'Sweets'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Jalebi');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Kheer', 'Creamy rice pudding with dry fruits', 40.00
FROM categories c
WHERE c.name = 'Sweets'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Kheer');

INSERT INTO food_items (category_id, name, description, price)
SELECT c.id, 'Ice Cream Cup', 'Single scoop vanilla or chocolate', 35.00
FROM categories c
WHERE c.name = 'Sweets'
  AND NOT EXISTS (SELECT 1 FROM food_items f WHERE f.category_id = c.id AND f.name = 'Ice Cream Cup');
