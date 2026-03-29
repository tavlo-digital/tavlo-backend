# Testing the Reviews & Feedback Page

## Overview
I've added 15 realistic fake reviews to the seed data so you can see how the Reviews & Feedback page looks with real data.

## How to Load the Reviews

1. **Navigate to the Vendor Dashboard**
   - Log in as a vendor
   - Go to the Reviews & Feedback page

2. **Reseed the Database** (if reviews don't appear)
   - Open your browser console
   - Run: `await fetch('https://[YOUR_PROJECT_ID].supabase.co/functions/v1/make-server-1dccd8d3/seed?force=true', { method: 'POST', headers: { Authorization: 'Bearer [ANON_KEY]' } })`
   - Or simply reload the page if the seed data loads automatically

## What You'll See

### Reviews Breakdown:
- **5-star reviews (5)**: Excellent experiences, some with replies
- **4-star reviews (3)**: Good experiences, mostly positive
- **3-star reviews (3)**: Mixed experiences, 2 need attention (recent, not replied)
- **2-star reviews (2)**: Negative experiences, need attention
- **1-star review (1)**: Critical review, needs attention

### "Needs Attention" Count:
The page will show **5 reviews** needing attention:
- Rating ≤ 3★
- Not replied yet
- Created within last 7 days

These are:
1. David W. (3★, 2 days ago) - slow service
2. Peter H. (2★, 1 day ago) - overcooked pasta
3. Lisa G. (2★, 3 days ago) - cold food
4. Anonymous (3★, 4 days ago) - average pizza
5. Anonymous (1★, 5 days ago) - wrong order

### Features You Can Test:

1. **Search** - Search for customer names or keywords in reviews
2. **Filter Tabs**:
   - All (15 total)
   - Positive (11 reviews with 4-5★)
   - Needs Attention (5 reviews)

3. **Reply Features**:
   - Click "Reply" on any unreplied review
   - See "Reply guidance" checklist
   - Test "Draft a polite reply" AI button
   - Post your reply

4. **Existing Replies**:
   - Some reviews already have replies to show the replied state

5. **Anonymous Reviews**:
   - 3 reviews are anonymous (labeled as "Guest review")
   - Tooltip explains they were submitted without login

6. **Review Context**:
   - Mock contextual data (service period, peak hours, wait times) is shown on each review

## Expected Stats:
- **Total Reviews**: 15
- **Average Rating**: ~3.9★
- **Positive Reviews**: 11
- **Needs Attention**: 5

## Notes:
- Reviews have realistic timestamps (1-15 days ago)
- Mix of replied and unreplied reviews
- Variety of rating levels for comprehensive testing
- Anonymous and registered customer reviews included
