export const mockExams = [
  {
    id: "1",
    title: "Test",
    durationSeconds: 100,
    difficulty: "Easy",
    audioUrl: "/audio/ListeningP3_1.mp3",
    transcript: `W1: Hey, Anne. Hey, Mark. I was thinking maybe we could work together for the final exams. You know, why go it alone when your best friends are right here?
W2: Okay with me. How about splitting the textbook up into three parts? We can each make notes on one part.
M: But there are eight sections. How are we going to split those in three?
W1: We could try to find a fourth person and take charge of two chapters each.
W2: I think we three work well together, and having a fourth person might disturb the group harmony.
M: I suppose you're right. Look, Chapter 1 is Introduction to Finance. We can skip that.
W2: Sure. It contains pretty basic stuff.
W1: Then Chapters 2 and 3 seem to form a neat topic area together. I'll prepare notes on those.
M: The next two chapters are on analysis. If you can take those, Anne, I can cover the remaining three chapters about stocks and bonds.
W2: Are you sure? That seems a lot for one person.
M: No problem. I wrote a report on the stock market, so I can have the notes done in a few days.
W1: Okay. We're ready to go.
W2: Let's get to it!`,
    questions: [
      {
        id: "q1",
        type: "multiple_choice",
        questionText: "How does Anne respond to the idea of having another group member?",
        options: [
          { id: "A", label: "She's fine either way." },
          { id: "B", label: "She asks for Mark's opinion." },
          { id: "C", label: "She supports it." },
          { id: "D", label: "She rejects it." }
        ],
        correctAnswer: "D",
        explanation:
          "Anne says a fourth member might disturb group harmony, which shows she rejects the proposal.",
        transcriptReference: "having a fourth person might disturb the group harmony"
      },
      {
        id: "q2",
        type: "multiple_choice",
        questionText: "What can be inferred about Mark?",
        options: [
          { id: "A", label: "He's familiar with the latter sections." },
          { id: "B", label: "He prefers exams to oral reports." },
          { id: "C", label: "He thinks the course is too easy." },
          { id: "D", label: "He invests in stocks himself." }
        ],
        correctAnswer: "A",
        explanation:
          "Mark says he can cover the remaining chapters and mentions a stock market report, implying familiarity with those sections.",
        transcriptReference: "I wrote a report on the stock market"
      },
      {
        id: "q3",
        type: "multiple_select",
        questionText: "Which actions are explicitly agreed in the conversation? (Choose TWO)",
        options: [
          { id: "A", label: "Skip Chapter 1." },
          { id: "B", label: "Invite a fourth member." },
          { id: "C", label: "Mark covers the remaining three chapters." },
          { id: "D", label: "Anne refuses analysis chapters." }
        ],
        correctAnswer: ["A", "C"],
        explanation:
          "They agree to skip Chapter 1, and Mark states he will cover the remaining three chapters on stocks and bonds.",
        transcriptReference: "We can skip that"
      },
      {
        id: "q4",
        type: "fill_blank",
        questionText: "Anne worries a fourth person might disturb group ______.",
        correctAnswer: "harmony",
        explanation:
          "Anne directly says adding a fourth person might disturb the group harmony.",
        transcriptReference: "disturb the group harmony"
      },
      {
        id: "q5",
        type: "matching",
        questionText: "Match each speaker with what they say or do.",
        leftItems: [
          { id: "L1", label: "W1" },
          { id: "L2", label: "W2 (Anne)" },
          { id: "L3", label: "Mark" }
        ],
        rightItems: [
          { id: "R1", label: "Suggests adding a fourth member." },
          { id: "R2", label: "Worries about group harmony." },
          { id: "R3", label: "Says he wrote a stock market report." }
        ],
        correctAnswer: { L1: "R1", L2: "R2", L3: "R3" },
        explanation:
          "W1 proposes a fourth member, W2 rejects due to harmony, and Mark references his report.",
        transcriptReference: "I wrote a report on the stock market"
      },
      {
        id: "q6",
        type: "ordering",
        questionText: "Put the study plan in the order it is finalized.",
        orderingItems: [
          { id: "O1", label: "Skip Chapter 1." },
          { id: "O2", label: "W1 prepares Chapters 2 and 3." },
          { id: "O3", label: "Anne takes the next two analysis chapters." },
          { id: "O4", label: "Mark covers the remaining three chapters." }
        ],
        correctAnswer: ["O1", "O2", "O3", "O4"],
        explanation:
          "That is the final sequence after they reject adding a fourth person.",
          transcriptReference: "Then Chapters 2 and 3 seem to form a neat topic area together"
      }
    ]
  },
  {
    id: "2",
    title: "RRR Film Discussion",
    durationSeconds: 191,
    difficulty: "Medium",
    audioUrl: "/audio/ListeningP3_2.mp3",
    transcript: `W1: For our film class this week, instead of the usual black and white classics, I asked you to watch the Indian movie RRR that came out a few years ago. So what are your thoughts? John?
M: Well, it might be a modern movie, but it was still set in the past! Actually, it taught me a lot about India in the 1920s. I know the plot and characters were all made up, but I still learned something about India's struggle for independence.
W1: In fact, it's based on two real people, both famous revolutionaries. However, there are no records of them meeting each other. So the plot is fictional, but not the two main characters. It imagines what would have happened if they'd ever met and become friends.
W2: I'm not sure the movie taught me anything. First of all, it was too violent. Were all those fight scenes really necessary? Secondly, the women seemed to be totally unimportant!
M: Yes, it was very much a film about men, with very weak roles for the women. There was a lot of action in the film, but that was hardly avoidable due to the subject matter. All in all, I think it had a powerful story, well told. And I loved those Indian touches: Hollywood would never make an action film with singing and dancing!
W2: Well, that's not surprising: It was made for an Indian audience. And while I suppose it should be praised for bringing Bollywood-style movies to a wider audience, for me, the songs and dances were totally out of place. I couldn't take them seriously.
W1: You said earlier that the movie wasn't informative. Could you say more about that?
W2: I think RRR oversimplified the real situation: the Indians were portrayed as heroes and the British rulers as villains. The truth is that the relationship between the British Empire and its Indian subjects was way more complicated than the movie shows.
W1: But the movie's been hugely successful outside of India. It's made over US$14 million in North America and been praised by some of Hollywood's biggest actors and directors. Why do you think that is?
M: Well, it's visually stunning, and the action sequences are amazing. And the underlying story of the fight between good and evil is universal.
W2: I have to admit that the costumes and photography were beautiful, but didn't you think it was way too long, at over three hours?
M: Honestly, no. I was on the edge of my seat the whole time.
W1: Very interesting! So what did the rest of you think?`,
    questions: [
      {
        id: "q1",
        type: "multiple_choice",
        questionText: "Which aspect of the movie RRR does the teacher refer to at the beginning of the discussion?",
        options: [
          { id: "A", label: "Who its directors were." },
          { id: "B", label: "When it was released." },
          { id: "C", label: "How long it took to produce." },
          { id: "D", label: "What influence it had." }
        ],
        correctAnswer: "B",
        explanation:
          "The teacher says the movie came out a few years ago, which refers to its release time.",
        transcriptReference: "came out a few years ago"
      },
      {
        id: "q2",
        type: "multiple_choice",
        questionText: "Why does the teacher mention the background of the movie's characters?",
        options: [
          { id: "A", label: "To show her passion for Indian movies." },
          { id: "B", label: "To test Daphne's knowledge about India." },
          { id: "C", label: "To explain the film's excellent ticket sales." },
          { id: "D", label: "To correct a misunderstanding John has." }
        ],
        correctAnswer: "D",
        explanation:
          "John says the characters were all made up, and the teacher corrects this by saying the two main figures are based on real revolutionaries.",
        transcriptReference: "In fact, it's based on two real people"
      },
      {
        id: "q3",
        type: "multiple_choice",
        questionText: "What is likely Daphne's opinion of the movie RRR?",
        options: [
          { id: "A", label: "It featured the wrong actors." },
          { id: "B", label: "It deserved the awards it was given." },
          { id: "C", label: "It distorted historical facts." },
          { id: "D", label: "It provided insight into politics." }
        ],
        correctAnswer: "C",
        explanation:
          "Daphne says the movie oversimplified reality and reduced a complicated historical relationship to heroes vs villains.",
        transcriptReference: "RRR oversimplified the real situation"
      },
      {
        id: "q4",
        type: "multiple_select",
        questionText: "What do John and Daphne agree on with regard to the movie? Choose TWO answers.",
        options: [
          { id: "A", label: "It doesn't have strong female characters." },
          { id: "B", label: "The length is acceptable." },
          { id: "C", label: "It doesn't have realistic fight scenes." },
          { id: "D", label: "The musical elements are effective." },
          { id: "E", label: "It has a compelling visual design." }
        ],
        correctAnswer: ["A", "E"],
        explanation:
          "Both note weak female roles, and both acknowledge visual strengths such as costumes, photography, and action visuals.",
        transcriptReference: "the women seemed to be totally unimportant"
      },
      {
        id: "q5",
        type: "fill_blank",
        questionText: "Daphne says the real relationship was far more ______ than the movie shows.",
        correctAnswer: "complicated",
        explanation:
          "She explicitly says the truth is way more complicated than what the movie presents.",
        transcriptReference: "way more complicated than the movie shows"
      },
      {
        id: "q6",
        type: "matching",
        questionText: "Match each speaker to their key point.",
        leftItems: [
          { id: "L1", label: "Teacher (W1)" },
          { id: "L2", label: "John (M)" },
          { id: "L3", label: "Daphne (W2)" }
        ],
        rightItems: [
          { id: "R1", label: "Says visuals are stunning and good-vs-evil is universal." },
          { id: "R2", label: "Says the film oversimplifies historical reality." },
          { id: "R3", label: "Clarifies two leads are based on real revolutionaries." }
        ],
        correctAnswer: { L1: "R3", L2: "R1", L3: "R2" },
        explanation:
          "W1 clarifies historical basis, John praises visuals and universal story, Daphne critiques historical simplification.",
        transcriptReference: "visually stunning"
      },
      {
        id: "q7",
        type: "ordering",
        questionText: "Put these discussion points in the order they appear.",
        orderingItems: [
          { id: "O1", label: "Teacher introduces RRR and asks for thoughts." },
          { id: "O2", label: "John says he learned about India in the 1920s." },
          { id: "O3", label: "Teacher clarifies two lead characters are based on real people." },
          { id: "O4", label: "Daphne criticizes violence and weak female roles." },
          { id: "O5", label: "John praises visuals and universal conflict." }
        ],
        correctAnswer: ["O1", "O2", "O3", "O4", "O5"],
        explanation:
          "These points follow the chronological flow of the classroom discussion.",
        transcriptReference: "So what are your thoughts? John?"
      }
    ]
  }
];
