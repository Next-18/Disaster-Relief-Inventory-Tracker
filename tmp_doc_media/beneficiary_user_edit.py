from pathlib import Path
p=Path('resources/js/Pages/Admin/Users.vue'); s=p.read_text(encoding='utf-8')
s=s.replace("    email: '',\n    password: '',", "    email: '',\n    username: '',\n    password: '',")
s=s.replace("    role: 'staff',\n});", "    role: 'staff',\n    status: 'Active',\n});")
s=s.replace("    form.email = user.email;\n    form.role = user.role;", "    form.email = user.email ?? '';\n    form.username = user.username ?? '';\n    form.role = user.role;\n    form.status = user.beneficiary?.status ?? 'Active';")
s=s.replace('<span class="tag success">Active</span>', '<span :class="[\'tag\', user.role === \'user\' && user.beneficiary?.status === \'Inactive\' ? \'warning\' : \'success\']">{{ user.role === \'user\' ? (user.beneficiary?.status ?? \'Unknown\') : \'Active\' }}</span>')
s=s.replace("<h3>{{ editingId ? 'Edit user account' : 'Add team account' }}</h3>\n            <p>{{ editingId ? 'Update account information and role.' : 'Staff can release packages. Volunteers can scan and verify beneficiaries.' }}</p>", "<h3>{{ editingId ? (form.role === 'user' ? 'Edit beneficiary account' : 'Edit team account') : 'Add team account' }}</h3>\n            <p>{{ editingId && form.role === 'user' ? 'Update the beneficiary profile and linked login account.' : editingId ? 'Update account information and role.' : 'Staff can release packages. Volunteers can scan and verify beneficiaries.' }}</p>")
s=s.replace('''            <label>Email address
                <input v-model="form.email" type="email" required placeholder="Enter email address" :class="{ 'input-error': form.errors.email }">
                <small v-if="form.errors.email" class="field-error">{{ form.errors.email }}</small>
            </label>''', '''            <label v-if="form.role !== 'user'">Email address
                <input v-model="form.email" type="email" required placeholder="Enter email address" :class="{ 'input-error': form.errors.email }">
                <small v-if="form.errors.email" class="field-error">{{ form.errors.email }}</small>
            </label>
            <label v-if="form.role === 'user'">Username
                <input v-model="form.username" type="text" maxlength="100" required autocomplete="username" placeholder="Enter username" :class="{ 'input-error': form.errors.username }">
                <small v-if="form.errors.username" class="field-error">{{ form.errors.username }}</small>
            </label>''')
s=s.replace('''            <label>Role
                <select v-model="form.role" required :class="{ 'input-error': form.errors.role }">''', '''            <label v-if="form.role !== 'user'">Role
                <select v-model="form.role" required :class="{ 'input-error': form.errors.role }">''')
s=s.replace('''                <small v-if="form.errors.role" class="field-error">{{ form.errors.role }}</small>
            </label>
            <div class="modal-actions">''', '''                <small v-if="form.errors.role" class="field-error">{{ form.errors.role }}</small>
            </label>
            <label v-if="form.role === 'user'">Status
                <select v-model="form.status" required :class="{ 'input-error': form.errors.status }">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <small v-if="form.errors.status" class="field-error">{{ form.errors.status }}</small>
            </label>
            <div class="modal-actions">''')
p.write_text(s,encoding='utf-8')

p=Path('app/Http/Controllers/AdminController.php'); s=p.read_text(encoding='utf-8')
needle='''    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate(['''
replacement='''    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'user') {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id)],
                'password' => ['nullable', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()],
                'status' => ['required', 'in:Active,Inactive'],
            ]);

            $beneficiary = $user->beneficiary;
            if (! $beneficiary) {
                return back()->withErrors(['name' => 'This beneficiary account is not linked to a beneficiary record.']);
            }

            if (Beneficiary::where('full_name', $data['name'])->where('id', '!=', $beneficiary->id)->exists()) {
                return back()->withInput()->withErrors(['name' => 'A beneficiary with this name already exists.']);
            }

            DB::transaction(function () use ($user, $beneficiary, $data) {
                $beneficiary->full_name = $data['name'];
                $beneficiary->status = $data['status'];
                $beneficiary->save();

                $user->name = $data['name'];
                $user->username = $data['username'];
                if (! empty($data['password'])) {
                    $user->password = bcrypt($data['password']);
                    $user->password_changed = true;
                }
                $user->save();
            });

            $this->logAudit('update', 'users', "Updated beneficiary account for: {$beneficiary->full_name}");

            return redirect()->route('admin.users')->with('success', 'Beneficiary account updated successfully.');
        }

        $data = $request->validate(['''
if needle not in s: raise SystemExit('Could not locate updateUser method signature')
s=s.replace(needle,replacement,1)
p.write_text(s,encoding='utf-8')
