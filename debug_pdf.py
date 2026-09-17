import pdfplumber
import sys

def debug_ts_pdf(pdf_path):
    print(f"--- Debugging {pdf_path} ---")
    try:
        with pdfplumber.open(pdf_path) as pdf:
            page = pdf.pages[0]
            tables = page.extract_tables()
            print(f"Found {len(tables)} tables on page 1")
            for t in tables:
                print(f"Table columns: {len(t[0]) if t else 0}")
                for row in t[:5]:
                    print(row)
    except Exception as e:
        print("Error:", e)

if __name__ == "__main__":
    debug_ts_pdf("TGEAPCET_2025_FINALPHASE_LASTRANKS.pdf")
