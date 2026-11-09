from pathlib import Path
for name in ['app/Http/Controllers/AdminController.php','app/Http/Controllers/Api/BeneficiaryController.php']:
 p=Path(name); s=p.read_text(encoding='utf-8').replace("'household_size' => ['nullable', 'integer', 'min:0', 'max:99']", "'household_size' => ['nullable', 'integer', 'min:1', 'max:99']"); p.write_text(s,encoding='utf-8')
p=Path('resources/js/Pages/Admin/Beneficiaries.vue'); s=p.read_text(encoding='utf-8')
s=s.replace("if (householdSize && !/^(?:0|[1-9]\\d?)$/.test(householdSize)) {", "if (householdSize && !/^(?:[1-9]|[1-9]\\d)$/.test(householdSize)) {")
s=s.replace('Household size must be a whole number from 0 to 99.', 'Household size must be a whole number from 1 to 99.')
s=s.replace('placeholder="0–99 members"', 'placeholder="1–99 members"')
p.write_text(s,encoding='utf-8')
