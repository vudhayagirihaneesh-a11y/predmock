import pdfplumber
import json
import re

def clean_rank(val):
    if not val:
        return None
    val = str(val).replace('\n', '').replace(',', '').strip()
    if val == '' or not val.isdigit():
        return None
    return int(val)

def parse_pdf(pdf_path, out_js_path, var_name, is_ts):
    print(f"Parsing {pdf_path}...")
    data = []
    try:
        with pdfplumber.open(pdf_path) as pdf:
            for page_idx, page in enumerate(pdf.pages):
                tables = page.extract_tables()
                for table in tables:
                    for row in table:
                        if not row or len(row) < 30:
                            continue
                        # Valid data rows usually have a digit somewhere or the first/second column is inst code
                        
                        if is_ts:
                            # TS format: 0:Inst Code, 1:Inst Name, ..., 6:Branch Code, 8:OC_B, 9:OC_G
                            # Skip header row
                            if "Inst" in str(row[0]) or "Code" in str(row[0]):
                                continue
                                
                            college_name = str(row[1]).replace('\n', ' ').strip()
                            branch = str(row[6]).replace('\n', ' ').strip()
                            
                            # SC is split into SC_I, SC_II, SC_III in TS
                            sc_b_1 = clean_rank(row[20])
                            sc_b_2 = clean_rank(row[22])
                            sc_b_3 = clean_rank(row[24])
                            valid_sc_b = [x for x in [sc_b_1, sc_b_2, sc_b_3] if x is not None]
                            sc_b = max(valid_sc_b) if valid_sc_b else None
                            
                            sc_g_1 = clean_rank(row[21])
                            sc_g_2 = clean_rank(row[23])
                            sc_g_3 = clean_rank(row[25])
                            valid_sc_g = [x for x in [sc_g_1, sc_g_2, sc_g_3] if x is not None]
                            sc_g = max(valid_sc_g) if valid_sc_g else None
                            
                            item = {
                                "college": college_name,
                                "branch": branch,
                                "oc_boys": clean_rank(row[8]),
                                "oc_girls": clean_rank(row[9]),
                                "bc_a_boys": clean_rank(row[10]),
                                "bc_a_girls": clean_rank(row[11]),
                                "bc_b_boys": clean_rank(row[12]),
                                "bc_b_girls": clean_rank(row[13]),
                                "bc_c_boys": clean_rank(row[14]),
                                "bc_c_girls": clean_rank(row[15]),
                                "bc_d_boys": clean_rank(row[16]),
                                "bc_d_girls": clean_rank(row[17]),
                                "bc_e_boys": clean_rank(row[18]),
                                "bc_e_girls": clean_rank(row[19]),
                                "sc_boys": sc_b,
                                "sc_girls": sc_g,
                                "sc_1_boys": sc_b_1,
                                "sc_1_girls": sc_g_1,
                                "sc_2_boys": sc_b_2,
                                "sc_2_girls": sc_g_2,
                                "sc_3_boys": sc_b_3,
                                "sc_3_girls": sc_g_3,
                                "st_boys": clean_rank(row[26]),
                                "st_girls": clean_rank(row[27]),
                                "ews_boys": clean_rank(row[28]),
                                "ews_girls": clean_rank(row[29]),
                            }
                            data.append(item)
                        else:
                            # AP format: 0:SNO, 1:INSTCODE, 2:NAME, ..., 11:branch, 12:EWS_BOYS, 14:OC_BOYS
                            if not str(row[0]).strip().isdigit():
                                continue
                                
                            college_name = str(row[2]).replace('\n', ' ').strip()
                            branch = str(row[11]).replace('\n', ' ').strip()
                            
                            item = {
                                "college": college_name,
                                "branch": branch,
                                "ews_boys": clean_rank(row[12]),
                                "ews_girls": clean_rank(row[13]),
                                "oc_boys": clean_rank(row[14]),
                                "oc_girls": clean_rank(row[15]),
                                "sc_boys": clean_rank(row[16]),
                                "sc_girls": clean_rank(row[17]),
                                "st_boys": clean_rank(row[18]),
                                "st_girls": clean_rank(row[19]),
                                "bc_a_boys": clean_rank(row[20]),
                                "bc_a_girls": clean_rank(row[21]),
                                "bc_b_boys": clean_rank(row[22]),
                                "bc_b_girls": clean_rank(row[23]),
                                "bc_c_boys": clean_rank(row[24]),
                                "bc_c_girls": clean_rank(row[25]),
                                "bc_d_boys": clean_rank(row[26]),
                                "bc_d_girls": clean_rank(row[27]),
                                "bc_e_boys": clean_rank(row[28]),
                                "bc_e_girls": clean_rank(row[29]),
                            }
                            data.append(item)
    except Exception as e:
        print(f"Error parsing {pdf_path}: {e}")
        return

    with open(out_js_path, 'w') as f:
        f.write(f"const {var_name} = {json.dumps(data)};\n")
    print(f"Saved {len(data)} records to {out_js_path}")

if __name__ == "__main__":
    parse_pdf("TGEAPCET_2025_FINALPHASE_LASTRANKS.pdf", "ts_cutoff_data.js", "tsCutoffData", True)
    parse_pdf("ap eamcet.pdf", "ap_cutoff_data.js", "apCutoffData", False)
