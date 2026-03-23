window.PracticeData = (function () {
  const modeMeta = {
    understand: {
      label: "Listening and Understand",
      answerType: "text"
    },
    retell: {
      label: "Listening and Retell",
      answerType: "audio"
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
      title: "Campus Library Orientation",
      type: "Campus",
      difficulty: "Easy",
      country: "UK",
      videoFile: "library_orientation.mp4",
      answerText: "The speaker introduces library zones, borrowing rules, and where students can get research support."
    },
    {
      id: "u2",
      mode: "understand",
      title: "Joining a Study Group",
      type: "Study Skills",
      difficulty: "Medium",
      country: "US",
      videoFile: "study_group.mp4",
      answerText: "The video explains how to find group members, assign roles, and set clear weekly learning goals."
    },
    {
      id: "r1",
      mode: "retell",
      title: "First Week Time Management",
      type: "Time Management",
      difficulty: "Medium",
      country: "Australia",
      videoFile: "time_management.mp4",
      answerAudioFile: "time_management_answer.mp3"
    },
    {
      id: "r2",
      mode: "retell",
      title: "How to Email a Professor",
      type: "Communication",
      difficulty: "Hard",
      country: "Canada",
      videoFile: "email_professor.mp4",
      answerAudioFile: "email_professor_answer.mp3"
    },
    {
      id: "s1",
      mode: "respond",
      title: "Dorm Conflict Case",
      type: "Campus",
      difficulty: "Easy",
      country: "US",
      videoFile: "dorm_conflict.mp4",
      question: "What would you say to solve the conflict politely?",
      answerText: "I would first acknowledge the roommate's concerns and propose a shared cleaning plan with a clear schedule."
    },
    {
      id: "s2",
      mode: "respond",
      title: "Class Presentation Q&A",
      type: "Academic",
      difficulty: "Hard",
      country: "UK",
      videoFile: "presentation_qa.mp4",
      question: "How would you respond if a classmate challenges your argument?",
      answerText: "I would thank them for the question, restate my key evidence, and admit limits while suggesting further research."
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
