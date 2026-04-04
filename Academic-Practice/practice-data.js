// 默认题库：understand / respond 各 12 条，顺序即列表顺序；更新内容只改本文件即可。
// 管理员在后台新增的条目请使用新 video_id（勿与下述 id 重复），会接在第 12 条之后展示。
window.PracticeData = (function () {
  const modeMeta = {
    understand: {
      label: "Listening and Understand",
      answerType: "text"
    },
    respond: {
      label: "Listening and Respond",
      answerType: "text"
    }
  };

  const videos = [
    {
      id: "u1",
      mode: "understand",
      title: "What is the secret to learning English?",
      type: "Campus",
      difficulty: "Easy",
      duration: "0-1min",
      source: "ELLLO",
      country: "Germany",
      author: "Christina",
      timeSpecific: "00:40",
      videoPath: "material/1.mp4",
      transcriptPath: "material/1_transcript.txt",
      transcriptText: "Hi, my name is Christina and I\u2019m from Germany. My question is what is the best way to learn English?\n\nWell, I was really lucky because I lived abroad in New Zealand for one year, so I\u2019ve spoken a lot of English there, and I really improved my English but what is really really important before you do that is learn some basics, so I learned the basics at school with a lot of listenings and readings, so exactly what those kind of videos are for, so I think it\u2019s a good combination if your first do the basic stuff and then just go over there and try to speak English all the time.",
      answerText: "The speaker introduces library zones, borrowing rules, and where students can get research support."
    },
    {
      id: "u2",
      mode: "understand",
      title: "Joining a Study Group",
      type: "Study Skills",
      difficulty: "Medium",
      duration: "2-3min",
      source: "ELLLO",
      country: "US",
      videoFile: "study_group.mp4",
      answerText: "The video explains how to find group members, assign roles, and set clear weekly learning goals."
    },
    {
      id: "u3",
      mode: "understand",
      title: "Freshman Welcome Week Tips",
      type: "Campus",
      difficulty: "Easy",
      duration: "0-1min",
      source: "OpenLearn",
      country: "Australia",
      videoFile: "welcome_week_tips.mp4",
      answerText: "The speaker shares quick tips on orientation events, map reading, and student help desks."
    },
    {
      id: "u4",
      mode: "understand",
      title: "How Office Hours Work",
      type: "Academic",
      difficulty: "Medium",
      duration: "1-2min",
      source: "ELLLO",
      country: "Canada",
      videoFile: "office_hours.mp4",
      answerText: "Students are encouraged to prepare questions and use office hours for assignment feedback."
    },
    {
      id: "u5",
      mode: "understand",
      title: "Lab Safety Briefing",
      type: "Academic",
      difficulty: "Hard",
      duration: "2-3min",
      source: "OpenLearn",
      country: "UK",
      videoFile: "lab_safety.mp4",
      answerText: "The video outlines safety signs, required equipment, and incident reporting procedures."
    },
    {
      id: "u6",
      mode: "understand",
      title: "Finding Part-time Jobs on Campus",
      type: "Campus",
      difficulty: "Easy",
      duration: "1-2min",
      source: "ELLLO",
      country: "US",
      videoFile: "part_time_jobs.mp4",
      answerText: "It introduces job boards, resume clinics, and scheduling around class commitments."
    },
    {
      id: "u7",
      mode: "understand",
      title: "Referencing and Plagiarism Basics",
      type: "Academic",
      difficulty: "Hard",
      duration: "2-3min",
      source: "OpenLearn",
      country: "New Zealand",
      videoFile: "referencing_basics.mp4",
      answerText: "The presenter compares citation styles and explains how to avoid accidental plagiarism."
    },
    {
      id: "u8",
      mode: "understand",
      title: "Using the Student Health Center",
      type: "Campus",
      difficulty: "Easy",
      duration: "0-1min",
      source: "OpenLearn",
      country: "Canada",
      videoFile: "health_center.mp4",
      answerText: "The clip covers booking methods, emergency lines, and available counseling services."
    },
    {
      id: "u9",
      mode: "understand",
      title: "Note-taking During Fast Lectures",
      type: "Academic",
      difficulty: "Medium",
      duration: "1-2min",
      source: "ELLLO",
      country: "US",
      videoFile: "note_taking_fast_lectures.mp4",
      answerText: "The speaker demonstrates shorthand strategies and post-class note organization."
    },
    {
      id: "u10",
      mode: "understand",
      title: "Managing Group Project Conflict",
      type: "Study Skills",
      difficulty: "Hard",
      duration: "2-3min",
      source: "ELLLO",
      country: "UK",
      videoFile: "group_project_conflict.mp4",
      answerText: "The video shows how teams can clarify roles, timelines, and communication rules."
    },
    {
      id: "u11",
      mode: "understand",
      title: "Public Transport for New Students",
      type: "Campus",
      difficulty: "Easy",
      duration: "1-2min",
      source: "OpenLearn",
      country: "Australia",
      videoFile: "public_transport.mp4",
      answerText: "It explains ticket apps, route planning, and common travel mistakes to avoid."
    },
    {
      id: "u12",
      mode: "understand",
      title: "Reading Academic Articles Efficiently",
      type: "Academic",
      difficulty: "Medium",
      duration: "2-3min",
      source: "ELLLO",
      country: "Ireland",
      videoFile: "reading_articles.mp4",
      answerText: "The clip presents a practical workflow for skimming abstracts and annotating key evidence."
    },
    {
      id: "s1",
      mode: "respond",
      title: "Would you rather write a paper or take a test?",
      type: "Campus",
      difficulty: "Medium",
      duration: "0-1min",
      source: "ELLLO",
      country: "Thailand",
      author: "On",
      timeSpecific: "00:37",
      videoPath: "material/2.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "Explain your choice and give one reason.",
      answerText: "I would choose writing a paper because it gives me more time to organize ideas and provide stronger evidence."
    },
    {
      id: "s2",
      mode: "respond",
      title: "Class Presentation Q&A",
      type: "Campus",
      difficulty: "Easy",
      duration: "0-1min",
      source: "ELLLO",
      country: "UK",
      videoFile: "presentation_qa.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "How would you respond if a classmate challenges your argument?",
      answerText: "I would thank them for the question, restate my key evidence, and admit limits while suggesting further research."
    },
    {
      id: "s3",
      mode: "respond",
      title: "Choosing a Club This Semester",
      type: "Campus",
      difficulty: "Easy",
      duration: "0-1min",
      source: "ELLLO",
      country: "Canada",
      videoFile: "presentation_qa.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "Which club would you join first and why?",
      answerText: "I would join a debate club to improve critical thinking and speaking confidence."
    },
    {
      id: "s4",
      mode: "respond",
      title: "Dorm Room Study Habits",
      type: "Campus",
      difficulty: "Medium",
      duration: "0-1min",
      source: "OpenLearn",
      country: "Australia",
      videoFile: "presentation_qa.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "What habit helps you study better in a shared room?",
      answerText: "I usually set a fixed quiet hour and wear headphones to stay focused."
    },
    {
      id: "s5",
      mode: "respond",
      title: "Managing Exam Stress",
      type: "Academic",
      difficulty: "Medium",
      duration: "1-2min",
      source: "ELLLO",
      country: "US",
      videoFile: "presentation_qa.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "What would you do one week before finals to reduce stress?",
      answerText: "I would make a realistic review plan and prioritize sleep to stay efficient."
    },
    {
      id: "s6",
      mode: "respond",
      title: "Group Project Communication",
      type: "Academic",
      difficulty: "Medium",
      duration: "1-2min",
      source: "OpenLearn",
      country: "UK",
      videoFile: "presentation_qa.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "How do you keep a group project on track?",
      answerText: "I set clear weekly goals and use short check-ins to update progress."
    },
    {
      id: "s7",
      mode: "respond",
      title: "Part-time Work Balance",
      type: "Campus",
      difficulty: "Easy",
      duration: "0-1min",
      source: "ELLLO",
      country: "Ireland",
      videoFile: "presentation_qa.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "How can students balance work and study time?",
      answerText: "I would limit shifts on weekdays and protect key study blocks."
    },
    {
      id: "s8",
      mode: "respond",
      title: "Asking for Professor Feedback",
      type: "Academic",
      difficulty: "Medium",
      duration: "1-2min",
      source: "ELLLO",
      country: "New Zealand",
      videoFile: "presentation_qa.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "What would you say when requesting assignment feedback?",
      answerText: "I would ask specific questions about weak sections and how to improve them."
    },
    {
      id: "s9",
      mode: "respond",
      title: "Adapting to a New City",
      type: "Campus",
      difficulty: "Easy",
      duration: "0-1min",
      source: "OpenLearn",
      country: "Germany",
      videoFile: "presentation_qa.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "What is one way to adapt quickly to a new city?",
      answerText: "I would learn transportation routes first because it makes daily life easier."
    },
    {
      id: "s10",
      mode: "respond",
      title: "Improving Listening Skills",
      type: "Academic",
      difficulty: "Hard",
      duration: "1-2min",
      source: "ELLLO",
      country: "Japan",
      videoFile: "presentation_qa.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "Which listening strategy works best for you?",
      answerText: "I replay short clips and note keywords before checking the transcript."
    },
    {
      id: "s11",
      mode: "respond",
      title: "Leading a Seminar Discussion",
      type: "Academic",
      difficulty: "Hard",
      duration: "1-2min",
      source: "OpenLearn",
      country: "Singapore",
      videoFile: "presentation_qa.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "How would you start a seminar discussion confidently?",
      answerText: "I would begin with one clear question and invite two viewpoints first."
    },
    {
      id: "s12",
      mode: "respond",
      title: "Preparing a Short Speech",
      type: "Campus",
      difficulty: "Medium",
      duration: "1-2min",
      source: "ELLLO",
      country: "France",
      videoFile: "presentation_qa.mp4",
      transcriptPath: "material/2_transcript.txt",
      transcriptText: "Hello, my name is On. I'm from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.",
      question: "How do you prepare a one-minute speech quickly?",
      answerText: "I focus on one message, three points, and one practical example."
    }
  ];

  const discussionTopics = [
    {
      id: "t1",
      title: "Making friends in the first month",
      description: "Share one concrete strategy that helped you start conversations on campus."
    },
    {
      id: "t2",
      title: "Balancing coursework and clubs",
      description: "Discuss how you allocate your time between academic tasks and extracurricular activities."
    },
    {
      id: "t3",
      title: "Coping with presentation anxiety",
      description: "Post advice about handling nervousness before speaking in front of class."
    },
    {
      id: "t4",
      title: "Choosing elective courses",
      description: "Recommend how first-year students can evaluate course workload and learning value."
    }
  ];

  return {
    modeMeta: modeMeta,
    videos: videos,
    discussionTopics: discussionTopics
  };
})();
