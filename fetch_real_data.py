import urllib.request
import re
import json
import os

def build_real_dataset():
    # Fetch real data from Github
    url = "https://raw.githubusercontent.com/ganeshreddychimmula/ts-eamcet-councelling-data/main/functionality/data/dataStore.js"
    req = urllib.request.Request(url)
    with urllib.request.urlopen(req) as response:
        content = response.read().decode('utf-8')
    
    # Extract existingCutoffData2023
    match = re.search(r'existingCutoffData2023:\s*\[(.*?)\]', content, re.DOTALL)
    if not match:
        print("Could not find data")
        return
    
    raw_data = match.group(1)
    
    # Simple regex to find all objects
    objects = re.findall(r'\{([^}]+)\}', raw_data)
    
    colleges = {}
    
    for obj in objects:
        c_match = re.search(r'collegeName:\s*"([^"]+)"', obj)
        b_match = re.search(r'branch:\s*"([^"]+)"', obj)
        cat_match = re.search(r'category:\s*"([^"]+)"', obj)
        rank_match = re.search(r'closingRank:\s*(\d+)', obj)
        
        if c_match and b_match and cat_match and rank_match:
            c = c_match.group(1)
            b = b_match.group(1)
            cat = cat_match.group(1)
            rank = int(rank_match.group(1))
            
            if c not in colleges:
                colleges[c] = {}
            if b not in colleges[c]:
                colleges[c][b] = {}
            
            colleges[c][b][cat] = rank
    
    # Format into our required structure
    formatted_data = []
    for c, branches in colleges.items():
        for b, cats in branches.items():
            
            # Map categories from their format to ours
            # Example their format: OC_GEN_R1, OC_GIRLS_R1, BC_A_GEN_R1, etc.
            # Fallback to defaults if a category is missing for this branch
            def get_cat(key1, key2, fallback_key, default_val=150000):
                if key1 in cats: return cats[key1]
                if key2 in cats: return cats[key2]
                
                # If neither exists, fallback to OC rank + offset if available
                if fallback_key in cats:
                    return cats[fallback_key]
                return default_val
            
            oc_b = get_cat("OC_GEN_R1", "OC_GEN_FP", "OC_GEN_R1", 10000)
            oc_g = get_cat("OC_GIRLS_R1", "OC_GIRLS_FP", "OC_GEN_R1", oc_b)
            
            bc_a_b = get_cat("BC_A_GEN_R1", "BC_A_GEN_FP", "OC_GEN_R1", oc_b)
            bc_a_g = get_cat("BC_A_GIRLS_R1", "BC_A_GIRLS_FP", "BC_A_GEN_R1", bc_a_b)
            
            bc_b_b = get_cat("BC_B_GEN_R1", "BC_B_GEN_FP", "OC_GEN_R1", oc_b)
            bc_b_g = get_cat("BC_B_GIRLS_R1", "BC_B_GIRLS_FP", "BC_B_GEN_R1", bc_b_b)
            
            sc_b = get_cat("SC_GEN_R1", "SC_GEN_FP", "OC_GEN_R1", oc_b)
            sc_g = get_cat("SC_GIRLS_R1", "SC_GIRLS_FP", "SC_GEN_R1", sc_b)
            
            st_b = get_cat("ST_GEN_R1", "ST_GEN_FP", "OC_GEN_R1", oc_b)
            st_g = get_cat("ST_GIRLS_R1", "ST_GIRLS_FP", "ST_GEN_R1", st_b)
            
            formatted_data.append({
                "college": c,
                "branch": b,
                "oc_boys": oc_b, "oc_girls": oc_g,
                "bc_a_boys": bc_a_b, "bc_a_girls": bc_a_g,
                "bc_b_boys": bc_b_b, "bc_b_girls": bc_b_g,
                "sc_boys": sc_b, "sc_girls": sc_g,
                "st_boys": st_b, "st_girls": st_g
            })
    
    with open("ts_cutoff_data.js", "w") as f:
        f.write(f"const tsCutoffData = {json.dumps(formatted_data)};\n")
        
    with open("ap_cutoff_data.js", "w") as f:
        f.write(f"const apCutoffData = {json.dumps(formatted_data)};\n")
        
build_real_dataset()
