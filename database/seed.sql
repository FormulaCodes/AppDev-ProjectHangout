
USE hangout_db;

INSERT INTO tags (name, slug) VALUES
('Cheap', 'cheap'),
('Study-Friendly', 'study-friendly'),
('Quiet', 'quiet'),
('Great Food', 'great-food'),
('Hidden Gem', 'hidden-gem');

INSERT INTO places (name, description, address, latitude, longitude) VALUES
(
    'Campus Study Cafe',
    'A sample cafe concept with seating suitable for studying.',
    'Near the campus entrance',
    14.5547,
    121.0244
),
(
    'Student Budget Meals',
    'An example affordable food spot for students.',
    'Near the student activity area',
    14.5539,
    121.0235
),
(
    'Quiet Corner',
    'A sample quiet location for reading and independent work.',
    'Near the campus library',
    14.5552,
    121.0251
);

INSERT INTO place_tags (place_id, tag_id)
SELECT p.id, t.id
FROM places p
JOIN tags t ON t.slug IN ('cheap', 'study-friendly')
WHERE p.name = 'Campus Study Cafe';

INSERT INTO place_tags (place_id, tag_id)
SELECT p.id, t.id
FROM places p
JOIN tags t ON t.slug IN ('cheap', 'great-food')
WHERE p.name = 'Student Budget Meals';

INSERT INTO place_tags (place_id, tag_id)
SELECT p.id, t.id
FROM places p
JOIN tags t ON t.slug IN ('quiet', 'study-friendly')
WHERE p.name = 'Quiet Corner';
