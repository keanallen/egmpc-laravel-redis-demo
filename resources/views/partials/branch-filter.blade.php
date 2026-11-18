<label for="branch_id">Branch
    <select name="branch_id" id="branch_id">
        @foreach($branches as $branch)
            <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
        @endforeach
    </select>
</label>
