// Lightweight customer care bot for Exam Prep Hub
// Works fully client-side (no API keys).
// Inject into profile.html chatbox.

(function () {
  if (window.ExamCareBot) return;

  const FAQ = [
    {
      id: 'greeting',
      patterns: [ /(^|\s)(hi|hello|hey|good morning|good afternoon|good evening|welcome)(\s|$)/i ],
      reply:
        "Hello! 👋 I can help you with mock tests, payments, login, or support. Ask me things like:\n• 'How do I unlock mocks?'\n• 'Why do I still see pay?'\n• 'I need help with login'" 
    },
    {
      id: 'unlock',
      patterns: [
        /unlock/i,
        /bought/i,
        /purchase/i,
        /payment/i,
        /paid/i,
        /mega bundle/i,
        /met package/i,
        /vit package/i,
        /srm package/i,
        /bitsat package/i,
        /jee package/i,
        /amrita package/i,
        /gitam package/i,
        /why.*locked/i,
        /can't access/i
      ],
      reply:
        "Your purchase will unlock after manual admin verification, which can take **up to 2 hours**. ⏳\n\n✓ If you just paid: Please be patient while we verify your UPI screenshot.\n✓ Check **My Profile** to see your status.\n✓ After approval: Click the **Mock Tests** button in your exam portal." 
    },
    {
      id: 'refunds',
      patterns: [ /refund/i, /money back/i, /cancel.*subscription/i, /return.*money/i ],
      reply:
        "⚠️ **Refund Policy:**\n\nAs per Section 4.3 of our Terms of Service:\n*\"Due to the digital, immediately consumable nature of the mock test data, all sales are absolute and final. No refunds will be issued once a package is marked 'approved'.\"*\n\nIf you believe there was a billing error or duplicate charge, please create a support ticket with your order ID."
    },
    {
      id: 'accessMocks',
      patterns: [ /how.*access|where.*mock|open mock|start mock|take test/i, /how do i/i ],
      reply:
        "📝 **To access mock tests:**\n1. Go to your exam predictor (VITEEE, BITSAT, SRM, MET, JEE, Amrita, or GITAM)\n2. Click the **Mock Tests** button\n3. If unlocked: See all tests and click **Start Test**\n4. If locked: Buy the package from the payment page\n\nEach predictor has its own mock series!" 
    },
    {
      id: 'repay',
      patterns: [ /repay|pay again|buy again|re-purchase|purchase twice|buy twice/i ],
      reply:
        "❌ You shouldn't need to pay twice!\n\n✓ If you already paid: Your purchase is saved in our database\n✓ Refresh the page or logout/login to see your unlocked tests\n✓ If it still shows as locked: Contact support immediately with your email\n\nNote: We sync purchases from our database automatically on each page." 
    },
    {
      id: 'repeats',
      patterns: [ /repeat/i, /same question/i, /repetition|duplicate|copy/i, /see.*again/i ],
      reply:
        "Questions are unique per mock test! 📚\n\n✓ Some topics overlap (it's the exam pattern!)\n✓ But question text and options should be different\n✓ If you see exact duplicates: Report in support\n\nTip: **Clear Results** button removes your attempt history so you can retry fresh." 
    },
    {
      id: 'tech',
      patterns: [ /not working|doesn\u2019t work|error|bug|issue|problem|slow|crash|freeze/i, /can't|cannot/i ],
      reply:
        "🛠️ **Quick fixes:**\n1. **Refresh** the page (Cmd+R or Ctrl+R)\n2. **Clear Cache** → Logout and login again\n3. **Try another browser** (Chrome, Firefox, Safari)\n4. **Check internet** speed\n\nIf it still fails: Open support ticket with screenshot + page name (e.g., mock-tests.html) + your email." 
    },
    {
      id: 'login',
      patterns: [ /login|sign in|otp|email code|verification|verify|register/i, /can't login|login fails/i ],
      reply:
        "📧 **Login Steps:**\n1. Enter your email\n2. Click **Send OTP** (check spam folder!)\n3. Enter the 6-digit code\n4. Done! ✓\n\n💡 Tips:\n• Check email spam folder\n• On dev mode: OTP shown on screen\n• Use same email for all purchases\n\nStill not working? Clear browser cache and try again." 
    },
    {
      id: 'performance',
      patterns: [ /calculator|performance|loading|slow|lag|speed/i, /timer/i ],
      reply:
        "⚡ **For better performance:**\n1. Close other tabs and apps\n2. Disable browser extensions\n3. Check internet speed (need 2+ Mbps)\n4. Use latest browser version\n\n💻 Virtual Calculator and Timer work offline. If stuck, refresh the page and your progress auto-saves!" 
    },
    {
      id: 'results',
      patterns: [ /result|score|performance|rank|percentile|statistics|analysis/i, /how did i do/i ],
      reply:
        "📊 **Mock Results:**\n✓ Your score saves automatically after submission\n✓ See all stats: Accuracy, Time, Ranking, Section-wise performance\n✓ Compare with other test-takers\n✓ Review answers: Correct, Incorrect, Skipped\n\n🔄 **Reattempt:** Click **Reattempt** button (previous score stays in history)" 
    },
    {
      id: 'packages',
      patterns: [ /package|plan|combo|bundle|price|cost|₹|rupees|how much/i, /what.*offer|difference/i ],
      reply:
        "💰 **Our Packages:**\n🎯 **Single Exam** (₹500): One exam series\n🌟 **Mega Bundle** (₹1500): All Exams (VIT + BITSAT + SRM + MET + JEE + AMRITA + GITAM)\n\n✓ Full solutions provided\n✓ Lifetime access\n✓ Instant unlock after payment approval\n\nChoose any package from the payment page!" 
    },
    {
      id: 'profile',
      patterns: [ /profile|account|settings|personal|data|change|edit/i, /my information/i ],
      reply:
        "👤 **Your Profile:**\n✓ View in top-right corner or click **My Profile**\n✓ See purchase history and unlock status\n✓ Check exam progress\n✓ Logout when done\n\nNote: Email is tied to your account. Use same email for all purchases!" 
    },
    {
      id: 'redirect',
      patterns: [ /mock\s*tests|mock\s*test|mock-test|mock tests/i, /predictor/i, /redirect/i, /go back|navigate/i, /how to start/i ],
      reply:
        "🎯 **Navigation:**\nStart from **Home Hub** (index.html)\n→ Click your exam (VITEEE, BITSAT, SRM, MET, JEE, Amrita, or GITAM)\n→ See predictor with rankings\n→ Click **Mock Tests** button\n→ View and take tests!\n\nEach exam has its own predictor and mock series." 
    },
    {
      id: 'thanks',
      patterns: [ /thank you|thanks|thx|appreciate/i ],
      reply:
        "You’re welcome! 😊 If you have any more questions about mock tests, payments, login, or support, just ask." 
    },
    {
      id: 'support',
      patterns: [ /support|ticket|help desk|assistance|escalate|not resolved/i, /contact/i ],
      reply:
        "🆘 **Get Support:**\n1. Try support chat (scroll up!)\n2. Create a **Support Ticket** from your profile\n3. Describe the issue clearly with screenshot\n4. We'll respond within 24 hours\n\n⏰ Can't wait? Click **Talk to Agent** for live chat." 
    }
  ];

  const escalateTriggers = [
    /agent|representative|human|support team/i,
    /talk to|speak to|chat with|connect/i,
    /not resolved|not helpful|doesn't help|didn't work|still stuck/i,
    /urgent|asap|emergency|critical/i,
    /complaint|angry|frustrated/i
  ];

  function normalize(str) {
    return String(str || '').trim();
  }

  function matchesAny(text, regexes) {
    return regexes.some((re) => re.test(text));
  }

  function getBotResponse(userText) {
    const text = normalize(userText);
    const lower = text.toLowerCase();

    if (!text) {
      return { 
        kind: 'answer', 
        reply: '👋 **Welcome to Exam Prep Hub Support!**\n\nCommon questions:\n💡 "How do I unlock mocks?"\n💡 "How to access mock tests?"\n💡 "Do I need to pay again?"\n💡 "Not working, help!"\n💡 "I need an agent"\n\nJust type your question!' 
      };
    }

    if (matchesAny(lower, escalateTriggers)) {
      return { 
        kind: 'escalate', 
        reply: '👨‍💼 **Connecting you to an agent...**\n\nPlease wait while we transfer you to our support team. You can also raise a **Support Ticket** from your profile for detailed help!' 
      };
    }

    for (const item of FAQ) {
      if (matchesAny(lower, item.patterns)) {
        if (item.id === 'unlock') {
          const hasAccess = ['met', 'vit', 'srm', 'bitsat', 'gitam', 'amrita', 'jee'].some(exam => localStorage.getItem(`${exam}_all_tests_unlocked`) === 'true');
          if (hasAccess) {
            return { kind: 'answer', reply: "🎉 It looks like the admin has already completed your payment and you already have access to your mock tests!\n\nPlease navigate to the exam portal and click 'Mock Tests'. If you still can't access them, please click 'Talk to Agent'." };
          }
        }
        return { kind: 'answer', reply: item.reply };
      }
    }

    // Default: try to be helpful
    return {
      kind: 'answer',
      reply:
        "🤔 I'm not 100% sure what you mean. Let me help!\n\n📝 Tell me:\n• **What page** are you on? (e.g., mock-tests.html, predictor)\n• **What did you expect** to happen?\n• **What actually happened**?\n\nOr ask me about: unlocking, packages, login, performance, results, or support!" 
    };
  }

  window.ExamCareBot = {
    getBotResponse
  };
})();
