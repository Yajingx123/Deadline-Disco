// questions.js
// 题库按难度分组：advanced / intermediate / beginner
// 每一类题型（choice / fill）在 advanced 内各 30 道（共 60）

const ADVANCED_QUESTIONS = [
  // --- Choice (30) ---
  {
    id: "a-c-01",
    type: "choice",
    question: "What is the closest synonym for <strong>'Ephemeral'</strong> in the context of digital trends?",
    options: { a: "Enduring and permanent", b: "Lasting for a very short time", c: "Widespread and pervasive", d: "Extremely complex" },
    answer: "b"
  },
  {
    id: "a-c-02",
    type: "choice",
    question: "A policy described as <strong>'draconian'</strong> is best characterized as:",
    options: { a: "Mild and forgiving", b: "Excessively harsh", c: "Unclear and ambiguous", d: "Temporary and short-lived" },
    answer: "b"
  },
  {
    id: "a-c-03",
    type: "choice",
    question: "Which word means to <strong>praise</strong> someone enthusiastically?",
    options: { a: "Castigate", b: "Excoriate", c: "Extol", d: "Impeach" },
    answer: "c"
  },
  {
    id: "a-c-04",
    type: "choice",
    question: "If evidence is <strong>'circumstantial'</strong>, it is:",
    options: { a: "Directly proving the claim", b: "Based on inference rather than direct observation", c: "Fabricated and false", d: "Too trivial to matter" },
    answer: "b"
  },
  {
    id: "a-c-05",
    type: "choice",
    question: "A <strong>'laconic'</strong> speaker tends to be:",
    options: { a: "Talkative and rambling", b: "Using very few words", c: "Overly emotional", d: "Highly technical" },
    answer: "b"
  },
  {
    id: "a-c-06",
    type: "choice",
    question: "Which word best describes a loud, harsh, discordant mixture of sounds?",
    options: { a: "Melody", b: "Harmony", c: "Cacophony", d: "Symphony" },
    answer: "c"
  },
  {
    id: "a-c-07",
    type: "choice",
    question: "To <strong>'mitigate'</strong> a risk is to:",
    options: { a: "Eliminate it completely", b: "Make it worse", c: "Lessen its severity", d: "Ignore it" },
    answer: "c"
  },
  {
    id: "a-c-08",
    type: "choice",
    question: "A <strong>'cogent'</strong> argument is:",
    options: { a: "Clear and convincing", b: "Long and repetitive", c: "Angry and insulting", d: "Based on superstition" },
    answer: "a"
  },
  {
    id: "a-c-09",
    type: "choice",
    question: "Which word most nearly means <strong>'to make unclear'</strong>?",
    options: { a: "Illuminate", b: "Obfuscate", c: "Validate", d: "Corroborate" },
    answer: "b"
  },
  {
    id: "a-c-10",
    type: "choice",
    question: "A <strong>'parsimonious'</strong> explanation is one that is:",
    options: { a: "Needlessly complex", b: "Based on many assumptions", c: "Simple and economical", d: "Completely speculative" },
    answer: "c"
  },
  {
    id: "a-c-11",
    type: "choice",
    question: "If someone is <strong>'equivocal'</strong>, they are:",
    options: { a: "Unambiguously decisive", b: "Intentionally vague or ambiguous", c: "Unusually brave", d: "Easily amused" },
    answer: "b"
  },
  {
    id: "a-c-12",
    type: "choice",
    question: "The discovery was a matter of <strong>'serendipity'</strong>, meaning it was:",
    options: { a: "Planned and deliberate", b: "A fortunate accident", c: "Inevitable", d: "A tragic mistake" },
    answer: "b"
  },
  {
    id: "a-c-13",
    type: "choice",
    question: "To <strong>'repudiate'</strong> a claim is to:",
    options: { a: "Publicly reject it", b: "Quietly support it", c: "Verify it", d: "Simplify it" },
    answer: "a"
  },
  {
    id: "a-c-14",
    type: "choice",
    question: "A <strong>'spurious'</strong> result is:",
    options: { a: "Genuine", b: "False or not what it appears to be", c: "Carefully measured", d: "Ethically justified" },
    answer: "b"
  },
  {
    id: "a-c-15",
    type: "choice",
    question: "If a detail is <strong>'peripheral'</strong>, it is:",
    options: { a: "Central and essential", b: "On the edge; not central", c: "Contradictory", d: "Mathematically exact" },
    answer: "b"
  },
  {
    id: "a-c-16",
    type: "choice",
    question: "To <strong>'ameliorate'</strong> a situation is to:",
    options: { a: "Make it worse", b: "Improve it", c: "Hide it", d: "Delay it" },
    answer: "b"
  },
  {
    id: "a-c-17",
    type: "choice",
    question: "A <strong>'trenchant'</strong> critique is:",
    options: { a: "Gentle and flattering", b: "Sharp and incisive", c: "Uninformed", d: "Overly sentimental" },
    answer: "b"
  },
  {
    id: "a-c-18",
    type: "choice",
    question: "If a statement is <strong>'tenuous'</strong>, it is:",
    options: { a: "Strongly supported", b: "Weak or flimsy", c: "Intentionally humorous", d: "Morally admirable" },
    answer: "b"
  },
  {
    id: "a-c-19",
    type: "choice",
    question: "Which word best means <strong>'to confirm with evidence'</strong>?",
    options: { a: "Corroborate", b: "Contemplate", c: "Negotiate", d: "Fabricate" },
    answer: "a"
  },
  {
    id: "a-c-20",
    type: "choice",
    question: "A person who is <strong>'pedantic'</strong> is overly concerned with:",
    options: { a: "Big-picture strategy", b: "Minor details and rules", c: "Compassion", d: "Physical strength" },
    answer: "b"
  },
  {
    id: "a-c-21",
    type: "choice",
    question: "To <strong>'preclude'</strong> an outcome is to:",
    options: { a: "Make it inevitable", b: "Prevent it from happening", c: "Celebrate it", d: "Delay it slightly" },
    answer: "b"
  },
  {
    id: "a-c-22",
    type: "choice",
    question: "A <strong>'ubiquitous'</strong> technology is:",
    options: { a: "Rare and hard to find", b: "Present everywhere", c: "Unreliable", d: "Illegal" },
    answer: "b"
  },
  {
    id: "a-c-23",
    type: "choice",
    question: "To <strong>'disseminate'</strong> information is to:",
    options: { a: "Hide it", b: "Spread it widely", c: "Prove it false", d: "Compress it" },
    answer: "b"
  },
  {
    id: "a-c-24",
    type: "choice",
    question: "A <strong>'capricious'</strong> decision is:",
    options: { a: "Carefully reasoned", b: "Impulsive and unpredictable", c: "Legally binding", d: "Scientifically tested" },
    answer: "b"
  },
  {
    id: "a-c-25",
    type: "choice",
    question: "To <strong>'admonish'</strong> someone is to:",
    options: { a: "Praise them", b: "Warn or reprimand them", c: "Ignore them", d: "Hire them" },
    answer: "b"
  },
  {
    id: "a-c-26",
    type: "choice",
    question: "A <strong>'dispassionate'</strong> analysis is:",
    options: { a: "Emotional and biased", b: "Objective and unemotional", c: "Sarcastic", d: "Careless" },
    answer: "b"
  },
  {
    id: "a-c-27",
    type: "choice",
    question: "A <strong>'prolific'</strong> author is one who:",
    options: { a: "Writes very little", b: "Produces a large amount of work", c: "Copies others", d: "Writes only fiction" },
    answer: "b"
  },
  {
    id: "a-c-28",
    type: "choice",
    question: "A <strong>'malevolent'</strong> intention is:",
    options: { a: "Kind-hearted", b: "Harmful or wishing evil", c: "Uninformed", d: "Ambivalent" },
    answer: "b"
  },
  {
    id: "a-c-29",
    type: "choice",
    question: "If a claim is <strong>'untenable'</strong>, it is:",
    options: { a: "Impossible to defend", b: "Highly profitable", c: "Likely to be true", d: "Easily remembered" },
    answer: "a"
  },
  {
    id: "a-c-30",
    type: "choice",
    question: "A <strong>'comprehensive'</strong> review is:",
    options: { a: "Brief and incomplete", b: "Thorough and covering everything", c: "Confusing", d: "Outdated" },
    answer: "b"
  },

  // --- Fill (30) ---
  {
    id: "a-f-01",
    type: "fill",
    question: "Fill in the blank with the appropriate word:<br>The speaker's attempt to ________ the truth only made the audience more suspicious of his motives.",
    answer: "obfuscate"
  },
  {
    id: "a-f-02",
    type: "fill",
    question: "Fill in the blank:<br>The committee voted to ________ the proposal after discovering a critical flaw in its assumptions.",
    answer: "repudiate"
  },
  {
    id: "a-f-03",
    type: "fill",
    question: "Fill in the blank:<br>Her ________ approach to solving the algorithm optimized the runtime significantly.",
    answer: "pragmatic"
  },
  {
    id: "a-f-04",
    type: "fill",
    question: "Fill in the blank:<br>The journalist tried to ________ the source's identity to protect them from retaliation.",
    answer: "conceal"
  },
  {
    id: "a-f-05",
    type: "fill",
    question: "Fill in the blank:<br>To ________ the spread of misinformation, the platform added clear citation requirements.",
    answer: "curb"
  },
  {
    id: "a-f-06",
    type: "fill",
    question: "Fill in the blank:<br>The researcher had to ________ the dataset before any meaningful analysis could begin.",
    answer: "sanitize"
  },
  {
    id: "a-f-07",
    type: "fill",
    question: "Fill in the blank:<br>Despite the setback, the team remained ________ and continued iterating on the design.",
    answer: "resolute"
  },
  {
    id: "a-f-08",
    type: "fill",
    question: "Fill in the blank:<br>The CEO's statement was deliberately ________, leaving room for multiple interpretations.",
    answer: "equivocal"
  },
  {
    id: "a-f-09",
    type: "fill",
    question: "Fill in the blank:<br>The scientist presented a ________ explanation that accounted for the results without extra assumptions.",
    answer: "parsimonious"
  },
  {
    id: "a-f-10",
    type: "fill",
    question: "Fill in the blank:<br>Good documentation can ________ confusion when new engineers join the team.",
    answer: "preclude"
  },
  {
    id: "a-f-11",
    type: "fill",
    question: "Fill in the blank:<br>The editor asked the author to ________ the argument with more concrete examples.",
    answer: "substantiate"
  },
  {
    id: "a-f-12",
    type: "fill",
    question: "Fill in the blank:<br>The report was criticized for its ________ claims, which lacked credible sources.",
    answer: "spurious"
  },
  {
    id: "a-f-13",
    type: "fill",
    question: "Fill in the blank:<br>The team worked to ________ the impact of the outage by rolling back the release.",
    answer: "mitigate"
  },
  {
    id: "a-f-14",
    type: "fill",
    question: "Fill in the blank:<br>He tried to ________ the debate by focusing on minor technicalities instead of the main issue.",
    answer: "derail"
  },
  {
    id: "a-f-15",
    type: "fill",
    question: "Fill in the blank:<br>The professor asked students to ________ the theme of the novel in one sentence.",
    answer: "encapsulate"
  },
  {
    id: "a-f-16",
    type: "fill",
    question: "Fill in the blank:<br>The audit revealed ________ spending that could not be justified by the project scope.",
    answer: "extraneous"
  },
  {
    id: "a-f-17",
    type: "fill",
    question: "Fill in the blank:<br>Her ________ remarks cut through the rhetoric and exposed the weak logic.",
    answer: "trenchant"
  },
  {
    id: "a-f-18",
    type: "fill",
    question: "Fill in the blank:<br>The diplomat attempted to ________ the conflict through careful negotiation.",
    answer: "ameliorate"
  },
  {
    id: "a-f-19",
    type: "fill",
    question: "Fill in the blank:<br>The intern's summary was too ________ and failed to capture any nuance.",
    answer: "simplistic"
  },
  {
    id: "a-f-20",
    type: "fill",
    question: "Fill in the blank:<br>The lab had to ________ the experiment after contamination was detected.",
    answer: "repeat"
  },
  {
    id: "a-f-21",
    type: "fill",
    question: "Fill in the blank:<br>To avoid bias, the reviewers were instructed to remain ________ throughout the evaluation.",
    answer: "dispassionate"
  },
  {
    id: "a-f-22",
    type: "fill",
    question: "Fill in the blank:<br>The speaker offered a ________ account, highlighting only facts and leaving out personal opinions.",
    answer: "detached"
  },
  {
    id: "a-f-23",
    type: "fill",
    question: "Fill in the blank:<br>The company will ________ a new policy to reduce repeated incidents.",
    answer: "implement"
  },
  {
    id: "a-f-24",
    type: "fill",
    question: "Fill in the blank:<br>The analyst tried to ________ the trend by comparing it with historical cycles.",
    answer: "contextualize"
  },
  {
    id: "a-f-25",
    type: "fill",
    question: "Fill in the blank:<br>The team had to ________ resources toward the most critical systems.",
    answer: "reallocate"
  },
  {
    id: "a-f-26",
    type: "fill",
    question: "Fill in the blank:<br>Her response was ________, avoiding a clear yes-or-no answer.",
    answer: "evasive"
  },
  {
    id: "a-f-27",
    type: "fill",
    question: "Fill in the blank:<br>The paper's central claim is ________ by multiple independent studies.",
    answer: "corroborated"
  },
  {
    id: "a-f-28",
    type: "fill",
    question: "Fill in the blank:<br>Because the connection was ________, the call kept dropping unexpectedly.",
    answer: "tenuous"
  },
  {
    id: "a-f-29",
    type: "fill",
    question: "Fill in the blank:<br>The leader issued an ________ to the team to stay vigilant during the transition.",
    answer: "admonition"
  },
  {
    id: "a-f-30",
    type: "fill",
    question: "Fill in the blank:<br>The guidelines are ________ enough to cover both standard and edge cases.",
    answer: "comprehensive"
  }
];

const INTERMEDIATE_QUESTIONS = [
  // --- Choice (30) ---
  {
    id: "i-c-01",
    type: "choice",
    question: "In academic writing, to <strong>'clarify'</strong> a point means to:",
    options: { a: "Make it confusing", b: "Make it clearer", c: "Remove it entirely", d: "Argue against it" },
    answer: "b"
  },
  {
    id: "i-c-02",
    type: "choice",
    question: "A <strong>'plausible'</strong> explanation is one that is:",
    options: { a: "Clearly impossible", b: "Believable", c: "Unrelated", d: "Offensive" },
    answer: "b"
  },
  {
    id: "i-c-03",
    type: "choice",
    question: "To <strong>'justify'</strong> a decision is to:",
    options: { a: "Prove it is reasonable", b: "Change it", c: "Ignore it", d: "Delay it" },
    answer: "a"
  },
  {
    id: "i-c-04",
    type: "choice",
    question: "If someone is <strong>'reluctant'</strong>, they are:",
    options: { a: "Eager", b: "Unwilling", c: "Confused", d: "Proud" },
    answer: "b"
  },
  {
    id: "i-c-05",
    type: "choice",
    question: "A <strong>'significant'</strong> result is:",
    options: { a: "Important", b: "Hidden", c: "Wrong", d: "Funny" },
    answer: "a"
  },
  {
    id: "i-c-06",
    type: "choice",
    question: "To <strong>'contrast'</strong> two ideas means to:",
    options: { a: "Show how they are different", b: "Show how they are identical", c: "Remove both", d: "Repeat them" },
    answer: "a"
  },
  {
    id: "i-c-07",
    type: "choice",
    question: "A <strong>'methodical'</strong> approach is:",
    options: { a: "Random", b: "Careful and systematic", c: "Careless", d: "Overly emotional" },
    answer: "b"
  },
  {
    id: "i-c-08",
    type: "choice",
    question: "To <strong>'interpret'</strong> data is to:",
    options: { a: "Destroy it", b: "Explain its meaning", c: "Copy it", d: "Hide it" },
    answer: "b"
  },
  {
    id: "i-c-09",
    type: "choice",
    question: "A <strong>'consistent'</strong> pattern is:",
    options: { a: "Changing constantly", b: "The same over time", c: "Unrelated", d: "Rare" },
    answer: "b"
  },
  {
    id: "i-c-10",
    type: "choice",
    question: "To <strong>'summarize'</strong> an article means to:",
    options: { a: "Write it again word-for-word", b: "Give the main points briefly", c: "Argue with it", d: "Translate it into code" },
    answer: "b"
  },
  {
    id: "i-c-11",
    type: "choice",
    question: "A <strong>'reliable'</strong> source is:",
    options: { a: "Trustworthy", b: "Entertaining", c: "Outdated", d: "Irrelevant" },
    answer: "a"
  },
  {
    id: "i-c-12",
    type: "choice",
    question: "To <strong>'analyze'</strong> a problem means to:",
    options: { a: "Look at its parts carefully", b: "Ignore it", c: "Guess randomly", d: "Celebrate it" },
    answer: "a"
  },
  {
    id: "i-c-13",
    type: "choice",
    question: "A <strong>'minor'</strong> issue is:",
    options: { a: "Very serious", b: "Not very important", c: "Impossible", d: "Permanent" },
    answer: "b"
  },
  {
    id: "i-c-14",
    type: "choice",
    question: "To <strong>'conclude'</strong> a study is to:",
    options: { a: "Begin it", b: "Finish it", c: "Forget it", d: "Hide it" },
    answer: "b"
  },
  {
    id: "i-c-15",
    type: "choice",
    question: "A <strong>'neutral'</strong> tone is:",
    options: { a: "Strongly biased", b: "Not taking sides", c: "Angry", d: "Sarcastic" },
    answer: "b"
  },
  {
    id: "i-c-16",
    type: "choice",
    question: "To <strong>'support'</strong> a claim in an essay usually means to:",
    options: { a: "Add evidence", b: "Delete it", c: "Insult it", d: "Avoid it" },
    answer: "a"
  },
  {
    id: "i-c-17",
    type: "choice",
    question: "If results are <strong>'accurate'</strong>, they are:",
    options: { a: "Correct", b: "Lucky", c: "Unclear", d: "Imaginary" },
    answer: "a"
  },
  {
    id: "i-c-18",
    type: "choice",
    question: "A <strong>'temporary'</strong> solution is:",
    options: { a: "Permanent", b: "Short-term", c: "Illegal", d: "Unrelated" },
    answer: "b"
  },
  {
    id: "i-c-19",
    type: "choice",
    question: "To <strong>'revise'</strong> a draft means to:",
    options: { a: "Improve and edit it", b: "Delete it", c: "Print it", d: "Hide it" },
    answer: "a"
  },
  {
    id: "i-c-20",
    type: "choice",
    question: "A <strong>'complex'</strong> topic is:",
    options: { a: "Easy and simple", b: "Complicated", c: "Boring", d: "Finished" },
    answer: "b"
  },
  {
    id: "i-c-21",
    type: "choice",
    question: "To <strong>'compare'</strong> two theories is to:",
    options: { a: "Discuss similarities and differences", b: "Ignore both", c: "Prove one false without reading", d: "Translate both into another language" },
    answer: "a"
  },
  {
    id: "i-c-22",
    type: "choice",
    question: "A <strong>'credible'</strong> argument is:",
    options: { a: "Believable", b: "Ridiculous", c: "Hidden", d: "Accidental" },
    answer: "a"
  },
  {
    id: "i-c-23",
    type: "choice",
    question: "To <strong>'emphasize'</strong> a point is to:",
    options: { a: "Make it less important", b: "Make it stand out", c: "Remove it", d: "Replace it with a joke" },
    answer: "b"
  },
  {
    id: "i-c-24",
    type: "choice",
    question: "A <strong>'relevant'</strong> detail is:",
    options: { a: "Connected to the topic", b: "Completely unrelated", c: "Dangerous", d: "Invisible" },
    answer: "a"
  },
  {
    id: "i-c-25",
    type: "choice",
    question: "To <strong>'maintain'</strong> a routine means to:",
    options: { a: "Keep it going", b: "Stop it", c: "Forget it", d: "Break it on purpose" },
    answer: "a"
  },
  {
    id: "i-c-26",
    type: "choice",
    question: "A <strong>'brief'</strong> explanation is:",
    options: { a: "Very long", b: "Short", c: "False", d: "Confusing" },
    answer: "b"
  },
  {
    id: "i-c-27",
    type: "choice",
    question: "To <strong>'evaluate'</strong> an option means to:",
    options: { a: "Judge it carefully", b: "Ignore it", c: "Copy it", d: "Destroy it" },
    answer: "a"
  },
  {
    id: "i-c-28",
    type: "choice",
    question: "A <strong>'major'</strong> change is:",
    options: { a: "Small", b: "Important and big", c: "Unnoticed", d: "Optional and minor" },
    answer: "b"
  },
  {
    id: "i-c-29",
    type: "choice",
    question: "To <strong>'expand'</strong> an idea is to:",
    options: { a: "Add more details", b: "Delete details", c: "Hide it", d: "Make it shorter" },
    answer: "a"
  },
  {
    id: "i-c-30",
    type: "choice",
    question: "A <strong>'logical'</strong> conclusion is one that:",
    options: { a: "Follows from the evidence", b: "Is random", c: "Is impossible", d: "Is unrelated to the argument" },
    answer: "a"
  },

  // --- Fill (30) ---
  { id: "i-f-01", type: "fill", question: "Fill in the blank:<br>Please ________ your main idea in one sentence.", answer: "summarize" },
  { id: "i-f-02", type: "fill", question: "Fill in the blank:<br>The author used statistics to ________ the claim.", answer: "support" },
  { id: "i-f-03", type: "fill", question: "Fill in the blank:<br>We should ________ the results carefully before making a decision.", answer: "analyze" },
  { id: "i-f-04", type: "fill", question: "Fill in the blank:<br>The two charts ________ the same general trend.", answer: "show" },
  { id: "i-f-05", type: "fill", question: "Fill in the blank:<br>Her explanation was clear and easy to ________.", answer: "understand" },
  { id: "i-f-06", type: "fill", question: "Fill in the blank:<br>The team will ________ the plan based on feedback.", answer: "revise" },
  { id: "i-f-07", type: "fill", question: "Fill in the blank:<br>This detail is not ________ to the research question.", answer: "relevant" },
  { id: "i-f-08", type: "fill", question: "Fill in the blank:<br>The report provides a ________ overview of the topic.", answer: "brief" },
  { id: "i-f-09", type: "fill", question: "Fill in the blank:<br>We need a ________ source, not a rumor.", answer: "reliable" },
  { id: "i-f-10", type: "fill", question: "Fill in the blank:<br>The evidence makes the explanation ________.", answer: "plausible" },
  { id: "i-f-11", type: "fill", question: "Fill in the blank:<br>Please ________ the difference between the two methods.", answer: "contrast" },
  { id: "i-f-12", type: "fill", question: "Fill in the blank:<br>The conclusion should ________ from your argument.", answer: "follow" },
  { id: "i-f-13", type: "fill", question: "Fill in the blank:<br>The instructions are easy to ________ if you read them slowly.", answer: "interpret" },
  { id: "i-f-14", type: "fill", question: "Fill in the blank:<br>We should ________ the risks before launching.", answer: "evaluate" },
  { id: "i-f-15", type: "fill", question: "Fill in the blank:<br>He was ________ to admit he was wrong.", answer: "reluctant" },
  { id: "i-f-16", type: "fill", question: "Fill in the blank:<br>The update is ________; we will replace it later.", answer: "temporary" },
  { id: "i-f-17", type: "fill", question: "Fill in the blank:<br>Her notes were neat and ________.", answer: "methodical" },
  { id: "i-f-18", type: "fill", question: "Fill in the blank:<br>We should ________ the key terms at the start.", answer: "clarify" },
  { id: "i-f-19", type: "fill", question: "Fill in the blank:<br>The findings were very ________ for future work.", answer: "significant" },
  { id: "i-f-20", type: "fill", question: "Fill in the blank:<br>The essay uses examples to ________ the idea.", answer: "expand" },
  { id: "i-f-21", type: "fill", question: "Fill in the blank:<br>A ________ argument avoids contradictions.", answer: "logical" },
  { id: "i-f-22", type: "fill", question: "Fill in the blank:<br>Her tone stayed ________ during the debate.", answer: "neutral" },
  { id: "i-f-23", type: "fill", question: "Fill in the blank:<br>This paragraph will ________ the results section.", answer: "conclude" },
  { id: "i-f-24", type: "fill", question: "Fill in the blank:<br>The point is ________ because it connects directly to the thesis.", answer: "relevant" },
  { id: "i-f-25", type: "fill", question: "Fill in the blank:<br>The model produced ________ predictions in our tests.", answer: "accurate" },
  { id: "i-f-26", type: "fill", question: "Fill in the blank:<br>We should ________ the two solutions before choosing one.", answer: "compare" },
  { id: "i-f-27", type: "fill", question: "Fill in the blank:<br>The speaker tried to ________ the key point with an example.", answer: "emphasize" },
  { id: "i-f-28", type: "fill", question: "Fill in the blank:<br>That is a ________ change, not a small adjustment.", answer: "major" },
  { id: "i-f-29", type: "fill", question: "Fill in the blank:<br>We must ________ our schedule to meet the deadline.", answer: "maintain" },
  { id: "i-f-30", type: "fill", question: "Fill in the blank:<br>The argument is ________ because it uses strong evidence.", answer: "credible" }
];

const BEGINNER_QUESTIONS = [
  // --- Choice (30) ---
  { id: "b-c-01", type: "choice", question: "The word <strong>'happy'</strong> is closest in meaning to:", options: { a: "sad", b: "glad", c: "angry", d: "tired" }, answer: "b" },
  { id: "b-c-02", type: "choice", question: "The opposite of <strong>'big'</strong> is:", options: { a: "small", b: "tall", c: "wide", d: "fast" }, answer: "a" },
  { id: "b-c-03", type: "choice", question: "If you <strong>'begin'</strong> something, you:", options: { a: "start it", b: "finish it", c: "forget it", d: "break it" }, answer: "a" },
  { id: "b-c-04", type: "choice", question: "A <strong>'quick'</strong> answer is:", options: { a: "slow", b: "fast", c: "late", d: "quiet" }, answer: "b" },
  { id: "b-c-05", type: "choice", question: "If you <strong>'choose'</strong>, you:", options: { a: "pick", b: "sleep", c: "hide", d: "cry" }, answer: "a" },
  { id: "b-c-06", type: "choice", question: "The word <strong>'easy'</strong> means:", options: { a: "hard", b: "simple", c: "noisy", d: "empty" }, answer: "b" },
  { id: "b-c-07", type: "choice", question: "To <strong>'help'</strong> someone means to:", options: { a: "hurt them", b: "assist them", c: "ignore them", d: "trick them" }, answer: "b" },
  { id: "b-c-08", type: "choice", question: "A <strong>'problem'</strong> is:", options: { a: "a difficulty", b: "a party", c: "a snack", d: "a song" }, answer: "a" },
  { id: "b-c-09", type: "choice", question: "If you are <strong>'tired'</strong>, you want to:", options: { a: "run", b: "rest", c: "shout", d: "dance" }, answer: "b" },
  { id: "b-c-10", type: "choice", question: "The word <strong>'buy'</strong> means:", options: { a: "sell", b: "get with money", c: "lose", d: "throw" }, answer: "b" },
  { id: "b-c-11", type: "choice", question: "A <strong>'teacher'</strong> is a person who:", options: { a: "cooks", b: "teaches", c: "drives", d: "paints" }, answer: "b" },
  { id: "b-c-12", type: "choice", question: "If something is <strong>'cold'</strong>, it is:", options: { a: "hot", b: "not warm", c: "bright", d: "heavy" }, answer: "b" },
  { id: "b-c-13", type: "choice", question: "To <strong>'look'</strong> at something means to:", options: { a: "see", b: "taste", c: "smell", d: "touch" }, answer: "a" },
  { id: "b-c-14", type: "choice", question: "A <strong>'book'</strong> is used to:", options: { a: "eat", b: "read", c: "sleep", d: "swim" }, answer: "b" },
  { id: "b-c-15", type: "choice", question: "The opposite of <strong>'up'</strong> is:", options: { a: "over", b: "down", c: "near", d: "open" }, answer: "b" },
  { id: "b-c-16", type: "choice", question: "If you <strong>'answer'</strong> a question, you:", options: { a: "ask it", b: "respond", c: "forget", d: "hide" }, answer: "b" },
  { id: "b-c-17", type: "choice", question: "A <strong>'friend'</strong> is someone you:", options: { a: "like", b: "fear", c: "avoid", d: "fight" }, answer: "a" },
  { id: "b-c-18", type: "choice", question: "If you <strong>'arrive'</strong>, you:", options: { a: "leave", b: "reach a place", c: "sleep", d: "fall" }, answer: "b" },
  { id: "b-c-19", type: "choice", question: "To <strong>'clean'</strong> means to:", options: { a: "make dirty", b: "make not dirty", c: "break", d: "paint" }, answer: "b" },
  { id: "b-c-20", type: "choice", question: "A <strong>'meal'</strong> is:", options: { a: "food you eat", b: "a game", c: "a job", d: "a car" }, answer: "a" },
  { id: "b-c-21", type: "choice", question: "The word <strong>'near'</strong> means:", options: { a: "close", b: "far", c: "late", d: "weak" }, answer: "a" },
  { id: "b-c-22", type: "choice", question: "To <strong>'listen'</strong> means to:", options: { a: "speak", b: "hear carefully", c: "run", d: "write" }, answer: "b" },
  { id: "b-c-23", type: "choice", question: "A <strong>'picture'</strong> is something you:", options: { a: "wear", b: "see", c: "eat", d: "drink" }, answer: "b" },
  { id: "b-c-24", type: "choice", question: "If you <strong>'forget'</strong>, you:", options: { a: "remember", b: "do not remember", c: "repeat", d: "explain" }, answer: "b" },
  { id: "b-c-25", type: "choice", question: "The word <strong>'finish'</strong> means:", options: { a: "start", b: "end", c: "mix", d: "push" }, answer: "b" },
  { id: "b-c-26", type: "choice", question: "A <strong>'question'</strong> is something you:", options: { a: "ask", b: "eat", c: "buy", d: "draw" }, answer: "a" },
  { id: "b-c-27", type: "choice", question: "If something is <strong>'safe'</strong>, it is:", options: { a: "dangerous", b: "not dangerous", c: "noisy", d: "broken" }, answer: "b" },
  { id: "b-c-28", type: "choice", question: "To <strong>'learn'</strong> means to:", options: { a: "teach", b: "gain knowledge", c: "lose money", d: "sleep" }, answer: "b" },
  { id: "b-c-29", type: "choice", question: "A <strong>'city'</strong> is usually:", options: { a: "very small", b: "a large town", c: "a single house", d: "a farm animal" }, answer: "b" },
  { id: "b-c-30", type: "choice", question: "The opposite of <strong>'open'</strong> is:", options: { a: "close", b: "closed", c: "big", d: "free" }, answer: "b" },

  // --- Fill (30) ---
  { id: "b-f-01", type: "fill", question: "Fill in the blank:<br>I ________ breakfast at 8 a.m.", answer: "eat" },
  { id: "b-f-02", type: "fill", question: "Fill in the blank:<br>She is my best ________.", answer: "friend" },
  { id: "b-f-03", type: "fill", question: "Fill in the blank:<br>Please ________ the door.", answer: "open" },
  { id: "b-f-04", type: "fill", question: "Fill in the blank:<br>He can ________ very fast.", answer: "run" },
  { id: "b-f-05", type: "fill", question: "Fill in the blank:<br>We ________ English at school.", answer: "learn" },
  { id: "b-f-06", type: "fill", question: "Fill in the blank:<br>They ________ to the park on Sunday.", answer: "go" },
  { id: "b-f-07", type: "fill", question: "Fill in the blank:<br>The cat is ________ the table.", answer: "under" },
  { id: "b-f-08", type: "fill", question: "Fill in the blank:<br>My name ________ Craig.", answer: "is" },
  { id: "b-f-09", type: "fill", question: "Fill in the blank:<br>It is ________ today, so wear a jacket.", answer: "cold" },
  { id: "b-f-10", type: "fill", question: "Fill in the blank:<br>I ________ a book every night.", answer: "read" },
  { id: "b-f-11", type: "fill", question: "Fill in the blank:<br>She ________ to music.", answer: "listens" },
  { id: "b-f-12", type: "fill", question: "Fill in the blank:<br>Please ________ your homework.", answer: "finish" },
  { id: "b-f-13", type: "fill", question: "Fill in the blank:<br>We ________ at the station at 6.", answer: "arrive" },
  { id: "b-f-14", type: "fill", question: "Fill in the blank:<br>He ________ water when he is thirsty.", answer: "drinks" },
  { id: "b-f-15", type: "fill", question: "Fill in the blank:<br>She ________ a question.", answer: "asks" },
  { id: "b-f-16", type: "fill", question: "Fill in the blank:<br>My phone is ________ my bag.", answer: "in" },
  { id: "b-f-17", type: "fill", question: "Fill in the blank:<br>They ________ a car last year.", answer: "bought" },
  { id: "b-f-18", type: "fill", question: "Fill in the blank:<br>We ________ the house every weekend.", answer: "clean" },
  { id: "b-f-19", type: "fill", question: "Fill in the blank:<br>He is ________ because he worked all day.", answer: "tired" },
  { id: "b-f-20", type: "fill", question: "Fill in the blank:<br>The bus stop is ________ my home.", answer: "near" },
  { id: "b-f-21", type: "fill", question: "Fill in the blank:<br>I do not ________ his name.", answer: "remember" },
  { id: "b-f-22", type: "fill", question: "Fill in the blank:<br>Please ________ carefully.", answer: "listen" },
  { id: "b-f-23", type: "fill", question: "Fill in the blank:<br>That place is very ________ to walk at night.", answer: "safe" },
  { id: "b-f-24", type: "fill", question: "Fill in the blank:<br>She feels ________ today.", answer: "happy" },
  { id: "b-f-25", type: "fill", question: "Fill in the blank:<br>The box is very ________.", answer: "big" },
  { id: "b-f-26", type: "fill", question: "Fill in the blank:<br>He lives in a big ________.", answer: "city" },
  { id: "b-f-27", type: "fill", question: "Fill in the blank:<br>Please ________ me your answer.", answer: "tell" },
  { id: "b-f-28", type: "fill", question: "Fill in the blank:<br>I will ________ now. See you later.", answer: "leave" },
  { id: "b-f-29", type: "fill", question: "Fill in the blank:<br>She forgot, so she will ________ it again.", answer: "learn" },
  { id: "b-f-30", type: "fill", question: "Fill in the blank:<br>We will ________ the meeting at 5.", answer: "end" }
];

const QUESTION_BANKS = {
  advanced: ADVANCED_QUESTIONS,
  intermediate: INTERMEDIATE_QUESTIONS,
  beginner: BEGINNER_QUESTIONS
};