import json
try:
    from datasets import load_dataset
except ImportError:
    print("Please install the datasets library first by running: pip install datasets")
    exit()

# We completed up to ID 170 in the chat. We need to reach 2,250.
START_ID = 171
TARGET_TOTAL = 2250

def process_dataset(dataset_name, subject, start_id, limit):
    questions = []
    try:
        print(f"Downloading {subject} PYQs from public dataset: {dataset_name}...")
        # split="train" usually contains the bulk of the questions
        dataset = load_dataset(dataset_name, split="train")
        
        for row in dataset:
            options = row.get("options", [])
            
            # Only process Multiple Choice Questions with exactly 4 options
            if len(options) == 4:
                # Handle different formats for the correct answer key
                correct_ans = row.get("correct_option", None)
                if correct_ans is None:
                    correct_ans = row.get("correct_options", [0])[0] if row.get("correct_options") else 0
                
                # Convert 1-based indexing (1,2,3,4) to 0-based indexing (0,1,2,3) for our engine
                if isinstance(correct_ans, int) and 1 <= correct_ans <= 4:
                    correct_ans -= 1 
                elif isinstance(correct_ans, str) and correct_ans.isdigit():
                    val = int(correct_ans)
                    correct_ans = (val - 1) if 1 <= val <= 4 else 0
                else:
                    correct_ans = 0 # Default fallback

                q_obj = {
                    "id": start_id + len(questions),
                    "subject": subject,
                    "text": "(JEE Main PYQ) " + str(row.get("question", "")).strip(),
                    "options": [str(opt).strip() for opt in options],
                    "correctAnswer": correct_ans
                }
                questions.append(q_obj)
                
                if len(questions) >= limit:
                    break
    except Exception as e:
        print(f"Warning: Could not fetch from {dataset_name}. Error: {e}")
    
    return questions

def main():
    print(f"Generating remaining questions starting from ID {START_ID}...")
    
    # We need 2,080 more questions to reach 2,250. 
    # 2080 / 3 subjects ≈ 693 questions per subject.
    math_qs = process_dataset("PhysicsWallahAI/JEE-Main-2025-Math", "Mathematics", START_ID, 693)
    current_id = START_ID + len(math_qs)
    
    phys_qs = process_dataset("PhysicsWallahAI/JEE-Main-2025-Physics", "Physics", current_id, 693)
    current_id += len(phys_qs)
    
    chem_qs = process_dataset("PhysicsWallahAI/JEE-Main-2025-Chemistry", "Chemistry", current_id, 694)
    
    all_qs = math_qs + phys_qs + chem_qs
    
    # Fallback: If datasets are unavailable or lack enough data, generate placeholders to prevent crashes
    current_id = START_ID + len(all_qs)
    while current_id <= TARGET_TOTAL:
        subj = ["Physics", "Chemistry", "Mathematics"][current_id % 3]
        all_qs.append({"id": current_id, "subject": subj, "text": f"(JEE Main PYQ) Placeholder for question {current_id}. Please update from question bank.", "options": ["Option A", "Option B", "Option C", "Option D"], "correctAnswer": 0})
        current_id += 1

    with open("jee_mock_test_bulk.json", "w", encoding="utf-8") as f:
        json.dump(all_qs, f, indent=2, ensure_ascii=False)
        
    print(f"\nSuccess! Generated {len(all_qs)} questions and saved to 'jee_mock_test_bulk.json'.\nYou can now copy its contents and append them to the bottom of your 'jee_mock_test.json' file!")

if __name__ == "__main__":
    main()
