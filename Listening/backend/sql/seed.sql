USE my_test_schema;

TRUNCATE TABLE `user`;

INSERT INTO `user` (username, password, email)
VALUES
('user1', '123456', 'user1@example.com'),
('user2', '654321', 'user2@example.com');

DELETE FROM questions WHERE exam_id IN ('exam-1', '1');
DELETE FROM exams WHERE id IN ('exam-1', '1');

INSERT INTO exams (id, title, difficulty, duration_seconds, audio_url, transcript)
VALUES
(
  '1',
  'Test',
  'Easy',
  100,
  '/audio/ListeningP3_1.mp3',
  'W1: Hey, Anne. Hey, Mark. I was thinking maybe we could work together for the final exams. You know, why go it alone when your best friends are right here?
W2: Okay with me. How about splitting the textbook up into three parts? We can each make notes on one part.
M: But there are eight sections. How are we going to split those in three?
W1: We could try to find a fourth person and take charge of two chapters each.
W2: I think we three work well together, and having a fourth person might disturb the group harmony.
M: I suppose you are right. Look, Chapter 1 is Introduction to Finance. We can skip that.
W2: Sure. It contains pretty basic stuff.
W1: Then Chapters 2 and 3 seem to form a neat topic area together. I will prepare notes on those.
M: The next two chapters are on analysis. If you can take those, Anne, I can cover the remaining three chapters about stocks and bonds.
W2: Are you sure? That seems a lot for one person.
M: No problem. I wrote a report on the stock market, so I can have the notes done in a few days.
W1: Okay. We are ready to go.
W2: Let us get to it!'
)
ON DUPLICATE KEY UPDATE
title = VALUES(title),
difficulty = VALUES(difficulty),
duration_seconds = VALUES(duration_seconds),
audio_url = VALUES(audio_url),
transcript = VALUES(transcript);

INSERT INTO questions (
  exam_id, question_key, type, question_text, options_json, left_items_json, right_items_json, ordering_items_json, correct_answer_json, explanation, transcript_reference, sort_order
) VALUES
(
  '1',
  'q1',
  'multiple_choice',
  'How does Anne respond to the idea of having another group member?',
  JSON_ARRAY(
    JSON_OBJECT('id','A','label','She is fine either way.'),
    JSON_OBJECT('id','B','label','She asks for Mark''s opinion.'),
    JSON_OBJECT('id','C','label','She supports it.'),
    JSON_OBJECT('id','D','label','She rejects it.')
  ),
  NULL, NULL, NULL,
  JSON_QUOTE('D'),
  'Anne says a fourth person might disturb group harmony, which indicates rejection of the proposal.',
  'having a fourth person might disturb the group harmony',
  1
),
(
  '1',
  'q2',
  'multiple_choice',
  'What can be inferred about Mark?',
  JSON_ARRAY(
    JSON_OBJECT('id','A','label','He is familiar with the latter sections.'),
    JSON_OBJECT('id','B','label','He prefers exams to oral reports.'),
    JSON_OBJECT('id','C','label','He thinks the course is too easy.'),
    JSON_OBJECT('id','D','label','He invests in stocks himself.')
  ),
  NULL, NULL, NULL,
  JSON_QUOTE('A'),
  'Mark says he can cover the remaining chapters and mentions his stock market report, so he is familiar with those sections.',
  'I wrote a report on the stock market',
  2
),
(
  '1',
  'q3',
  'multiple_select',
  'Which actions are explicitly agreed in the conversation? (Choose TWO)',
  JSON_ARRAY(
    JSON_OBJECT('id','A','label','Skip Chapter 1.'),
    JSON_OBJECT('id','B','label','Invite a fourth member.'),
    JSON_OBJECT('id','C','label','Mark covers the remaining three chapters.'),
    JSON_OBJECT('id','D','label','Anne refuses analysis chapters.')
  ),
  NULL, NULL, NULL,
  JSON_ARRAY('A','C'),
  'They agree to skip Chapter 1, and Mark says he will cover the remaining three chapters.',
  'We can skip that',
  3
),
(
  '1',
  'q4',
  'fill_blank',
  'Anne worries a fourth person might disturb group ______.',
  NULL, NULL, NULL, NULL,
  JSON_QUOTE('harmony'),
  'Anne directly says a fourth person might disturb group harmony.',
  'disturb the group harmony',
  4
),
(
  '1',
  'q5',
  'matching',
  'Match each speaker with what they say or do.',
  NULL,
  JSON_ARRAY(
    JSON_OBJECT('id','L1','label','W1'),
    JSON_OBJECT('id','L2','label','W2 (Anne)'),
    JSON_OBJECT('id','L3','label','Mark')
  ),
  JSON_ARRAY(
    JSON_OBJECT('id','R1','label','Suggests adding a fourth member.'),
    JSON_OBJECT('id','R2','label','Worries about group harmony.'),
    JSON_OBJECT('id','R3','label','Says he wrote a stock market report.')
  ),
  NULL,
  JSON_OBJECT('L1','R1','L2','R2','L3','R3'),
  'W1 proposes a fourth person, Anne objects for harmony, Mark cites his report.',
  'I wrote a report on the stock market',
  5
),
(
  '1',
  'q6',
  'ordering',
  'Put the study plan in the order it is finalized.',
  NULL, NULL, NULL,
  JSON_ARRAY(
    JSON_OBJECT('id','O1','label','Skip Chapter 1.'),
    JSON_OBJECT('id','O2','label','W1 prepares Chapters 2 and 3.'),
    JSON_OBJECT('id','O3','label','Anne takes the next two analysis chapters.'),
    JSON_OBJECT('id','O4','label','Mark covers the remaining three chapters.')
  ),
  JSON_ARRAY('O1','O2','O3','O4'),
  'This is the finalized sequence after they reject adding a fourth member.',
  'Then Chapters 2 and 3 seem to form a neat topic area together',
  6
)
ON DUPLICATE KEY UPDATE
type = VALUES(type),
question_text = VALUES(question_text),
options_json = VALUES(options_json),
left_items_json = VALUES(left_items_json),
right_items_json = VALUES(right_items_json),
ordering_items_json = VALUES(ordering_items_json),
correct_answer_json = VALUES(correct_answer_json),
explanation = VALUES(explanation),
transcript_reference = VALUES(transcript_reference),
sort_order = VALUES(sort_order);

DELETE FROM questions WHERE exam_id = '2';
DELETE FROM exams WHERE id = '2';

INSERT INTO exams (id, title, difficulty, duration_seconds, audio_url, transcript)
VALUES
(
  '2',
  'RRR Film Discussion',
  'Medium',
  191,
  '/audio/ListeningP3_2.mp3',
  'W1: For our film class this week, instead of the usual black and white classics, I asked you to watch the Indian movie RRR that came out a few years ago. So what are your thoughts? John?
M: Well, it might be a modern movie, but it was still set in the past! Actually, it taught me a lot about India in the 1920s. I know the plot and characters were all made up, but I still learned something about India''s struggle for independence.
W1: In fact, it''s based on two real people, both famous revolutionaries. However, there are no records of them meeting each other. So the plot is fictional, but not the two main characters. It imagines what would have happened if they''d ever met and become friends.
W2: I''m not sure the movie taught me anything. First of all, it was too violent. Were all those fight scenes really necessary? Secondly, the women seemed to be totally unimportant!
M: Yes, it was very much a film about men, with very weak roles for the women. There was a lot of action in the film, but that was hardly avoidable due to the subject matter. All in all, I think it had a powerful story, well told. And I loved those Indian touches: Hollywood would never make an action film with singing and dancing!
W2: Well, that''s not surprising: It was made for an Indian audience. And while I suppose it should be praised for bringing Bollywood-style movies to a wider audience, for me, the songs and dances were totally out of place. I couldn''t take them seriously.
W1: You said earlier that the movie wasn''t informative. Could you say more about that?
W2: I think RRR oversimplified the real situation: the Indians were portrayed as heroes and the British rulers as villains. The truth is that the relationship between the British Empire and its Indian subjects was way more complicated than the movie shows.
W1: But the movie''s been hugely successful outside of India. It''s made over US$14 million in North America and been praised by some of Hollywood''s biggest actors and directors. Why do you think that is?
M: Well, it''s visually stunning, and the action sequences are amazing. And the underlying story of the fight between good and evil is universal.
W2: I have to admit that the costumes and photography were beautiful, but didn''t you think it was way too long, at over three hours?
M: Honestly, no. I was on the edge of my seat the whole time.
W1: Very interesting! So what did the rest of you think?'
)
ON DUPLICATE KEY UPDATE
title = VALUES(title),
difficulty = VALUES(difficulty),
duration_seconds = VALUES(duration_seconds),
audio_url = VALUES(audio_url),
transcript = VALUES(transcript);

INSERT INTO questions (
  exam_id, question_key, type, question_text, options_json, left_items_json, right_items_json, ordering_items_json, correct_answer_json, explanation, transcript_reference, sort_order
) VALUES
(
  '2',
  'q1',
  'multiple_choice',
  'Which aspect of the movie RRR does the teacher refer to at the beginning of the discussion?',
  JSON_ARRAY(
    JSON_OBJECT('id','A','label','Who its directors were.'),
    JSON_OBJECT('id','B','label','When it was released.'),
    JSON_OBJECT('id','C','label','How long it took to produce.'),
    JSON_OBJECT('id','D','label','What influence it had.')
  ),
  NULL, NULL, NULL,
  JSON_QUOTE('B'),
  'The teacher says it came out a few years ago, which points to release time.',
  'came out a few years ago',
  1
),
(
  '2',
  'q2',
  'multiple_choice',
  'Why does the teacher mention the background of the movie''s characters?',
  JSON_ARRAY(
    JSON_OBJECT('id','A','label','To show her passion for Indian movies.'),
    JSON_OBJECT('id','B','label','To test Daphne''s knowledge about India.'),
    JSON_OBJECT('id','C','label','To explain the film''s excellent ticket sales.'),
    JSON_OBJECT('id','D','label','To correct a misunderstanding John has.')
  ),
  NULL, NULL, NULL,
  JSON_QUOTE('D'),
  'John says characters were made up, and the teacher corrects that the two leads are based on real revolutionaries.',
  'based on two real people',
  2
),
(
  '2',
  'q3',
  'multiple_choice',
  'What is likely Daphne''s opinion of the movie RRR?',
  JSON_ARRAY(
    JSON_OBJECT('id','A','label','It featured the wrong actors.'),
    JSON_OBJECT('id','B','label','It deserved the awards it was given.'),
    JSON_OBJECT('id','C','label','It distorted historical facts.'),
    JSON_OBJECT('id','D','label','It provided insight into politics.')
  ),
  NULL, NULL, NULL,
  JSON_QUOTE('C'),
  'Daphne says the movie oversimplified reality and distorted a more complicated historical relationship.',
  'oversimplified the real situation',
  3
),
(
  '2',
  'q4',
  'multiple_select',
  'What do John and Daphne agree on with regard to the movie? Choose TWO answers.',
  JSON_ARRAY(
    JSON_OBJECT('id','A','label','It doesn''t have strong female characters.'),
    JSON_OBJECT('id','B','label','The length is acceptable.'),
    JSON_OBJECT('id','C','label','It doesn''t have realistic fight scenes.'),
    JSON_OBJECT('id','D','label','The musical elements are effective.'),
    JSON_OBJECT('id','E','label','It has a compelling visual design.')
  ),
  NULL, NULL, NULL,
  JSON_ARRAY('A','E'),
  'Both point out weak female roles and both acknowledge visual strengths in costumes, photography, and action scenes.',
  'women seemed to be totally unimportant',
  4
),
(
  '2',
  'q5',
  'fill_blank',
  'Daphne says the real relationship was far more ______ than the movie shows.',
  NULL, NULL, NULL, NULL,
  JSON_QUOTE('complicated'),
  'She says the truth is way more complicated than what the movie presents.',
  'way more complicated than the movie shows',
  5
),
(
  '2',
  'q6',
  'matching',
  'Match each speaker to their key point.',
  NULL,
  JSON_ARRAY(
    JSON_OBJECT('id','L1','label','Teacher (W1)'),
    JSON_OBJECT('id','L2','label','John (M)'),
    JSON_OBJECT('id','L3','label','Daphne (W2)')
  ),
  JSON_ARRAY(
    JSON_OBJECT('id','R1','label','Says visuals are stunning and good-vs-evil is universal.'),
    JSON_OBJECT('id','R2','label','Says the film oversimplifies historical reality.'),
    JSON_OBJECT('id','R3','label','Clarifies two leads are based on real revolutionaries.')
  ),
  NULL,
  JSON_OBJECT('L1','R3','L2','R1','L3','R2'),
  'W1 clarifies historical basis, John praises visuals and universal story, Daphne critiques simplification.',
  'visually stunning',
  6
),
(
  '2',
  'q7',
  'ordering',
  'Put these discussion points in the order they appear.',
  NULL, NULL, NULL,
  JSON_ARRAY(
    JSON_OBJECT('id','O1','label','Teacher introduces RRR and asks for thoughts.'),
    JSON_OBJECT('id','O2','label','John says he learned about India in the 1920s.'),
    JSON_OBJECT('id','O3','label','Teacher clarifies two lead characters are based on real people.'),
    JSON_OBJECT('id','O4','label','Daphne criticizes violence and weak female roles.'),
    JSON_OBJECT('id','O5','label','John praises visuals and universal conflict.')
  ),
  JSON_ARRAY('O1','O2','O3','O4','O5'),
  'This follows the chronological flow of the classroom discussion.',
  'So what are your thoughts? John?',
  7
)
ON DUPLICATE KEY UPDATE
type = VALUES(type),
question_text = VALUES(question_text),
options_json = VALUES(options_json),
left_items_json = VALUES(left_items_json),
right_items_json = VALUES(right_items_json),
ordering_items_json = VALUES(ordering_items_json),
correct_answer_json = VALUES(correct_answer_json),
explanation = VALUES(explanation),
transcript_reference = VALUES(transcript_reference),
sort_order = VALUES(sort_order);
