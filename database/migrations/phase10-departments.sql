INSERT INTO departments (name, slug)
VALUES
    ('Artificial Intelligence and Data Science (AI & DS)', 'artificial-intelligence-and-data-science'),
    ('Civil Engineering', 'civil-engineering'),
    ('Computer Science and Engineering (CSE)', 'computer-science-and-engineering'),
    ('Electrical and Electronics Engineering (EEE)', 'electrical-and-electronics-engineering'),
    ('Electronics and Communication Engineering (ECE)', 'electronics-and-communication-engineering'),
    ('Mechanical Engineering', 'mechanical-engineering')
ON DUPLICATE KEY UPDATE name = VALUES(name);
