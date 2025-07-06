import json

def write_html_from_json(json_file_path, output_html_path):
    with open(json_file_path, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    html_content = data.get('page', '')
    
    with open(output_html_path, 'w', encoding='utf-8') as f:
        f.write(html_content)

if __name__ == "__main__":
    write_html_from_json('resp_example.json', 'resp.html') 