-- 单词初始数据（20 个：10 日常 + 10 专业，专业词书共享同一批动词）
-- 依赖：001_schema.sql, 002_seed_wordbooks.sql 已执行
USE vocab_dd;

-- Daily 词书：水果类
INSERT INTO `words` (`word`, `phonetic`, `meaning`, `sentence`, `image_url`, `audio_url`) VALUES
('apple', '/ˈæpl/', 'n. a round fruit with red or green skin', 'I have an apple for lunch.', 'docs/images1/301.png', NULL),
('banana', '/bəˈnɑːnə/', 'n. a long yellow fruit', 'Bananas are rich in potassium.', 'docs/images1/302.png', NULL),
('orange', '/ˈɒrɪndʒ/', 'n. a round citrus fruit', 'She squeezed fresh orange juice.', 'docs/images1/303.png', NULL),
('grape', '/ɡreɪp/', 'n. a small round fruit that grows in bunches', 'We picked grapes from the vine.', 'docs/images1/304.png', NULL),
('pear', '/peə/', 'n. a sweet fruit with a narrow top', 'This pear is ripe and juicy.', 'docs/images1/305.png', NULL),
('peach', '/piːtʃ/', 'n. a soft round fruit with a stone', 'Peaches taste best in summer.', 'docs/images1/306.png', NULL),
('watermelon', '/ˈwɔːtəmelən/', 'n. a large green fruit with red flesh', 'We shared a cold watermelon.', 'docs/images1/307.png', NULL),
('strawberry', '/ˈstrɔːbəri/', 'n. a small red fruit with seeds on the outside', 'Strawberry is my favourite flavour.', 'docs/images1/308.png', NULL),
('pineapple', '/ˈpaɪnæpl/', 'n. a tropical fruit with a rough skin', 'Pineapple works well in smoothies.', 'docs/images1/309.png', NULL),
('mango', '/ˈmæŋɡəʊ/', 'n. a tropical fruit with yellow flesh', 'Mango is sweet and refreshing.', 'docs/images1/310.png', NULL);

-- CS / 专业词书共享：动词类
INSERT INTO `words` (`word`, `phonetic`, `meaning`, `sentence`, `image_url`, `audio_url`) VALUES
('run', '/rʌn/', 'v. to move quickly on foot', 'I run every morning.', 'docs/images2/331.png', NULL),
('eat', '/iːt/', 'v. to take in food', 'We eat dinner at seven.', 'docs/images2/332.png', NULL),
('see', '/siː/', 'v. to perceive with the eyes', 'I can see the mountains from here.', 'docs/images2/333.png', NULL),
('go', '/ɡəʊ/', 'v. to move or travel', 'Let us go to the library.', 'docs/images2/334.png', NULL),
('do', '/duː/', 'v. to perform an action', 'What do you do at weekends?', 'docs/images2/335.png', NULL),
('play', '/pleɪ/', 'v. to take part in a game or activity', 'Children play in the park.', 'docs/images2/336.png', NULL),
('read', '/riːd/', 'v. to look at and understand written words', 'I read for half an hour before bed.', 'docs/images2/337.png', NULL),
('write', '/raɪt/', 'v. to form letters or words', 'Please write your name here.', 'docs/images2/338.png', NULL),
('listen', '/ˈlɪsn/', 'v. to pay attention to sound', 'Listen to the teacher carefully.', 'docs/images2/339.png', NULL),
('watch', '/wɒtʃ/', 'v. to look at for a period of time', 'We watch a film every Friday.', 'docs/images2/340.png', NULL);
