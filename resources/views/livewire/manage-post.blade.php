<div>

    <div class="bg-white rounded-lg shadow-md p-8">
        <h2 class="text-2xl font-bold mb-6 text-gray-800">Upload File</h2>

        @if (session()->has('message'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('message') }}
            </div>
        @endif

        @if ($uploadedFile)
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
                File saved to: {{ $uploadedFile }}
                <br>
                <a href="{{ Storage::url($uploadedFile) }}" target="_blank" class="underline">View File</a>
            </div>
        @endif

        <form wire:submit.prevent="save">
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center bg-gray-50">
                
                <input 
                    type="file" 
                    wire:model="file" 
                    id="fileInput"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                >

                @error('file') 
                    <span class="text-red-500 text-sm mt-2 block">{{ $message }}</span> 
                @enderror

                <div wire:loading wire:target="file" class="mt-4">
                    <p class="text-blue-600">Uploading...</p>
                </div>

                @if ($file)
                    <div class="mt-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-700">Preview:</h3>
                        
                        @if (in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                            <img src="{{ $file->temporaryUrl() }}" class="max-w-full max-h-96 mx-auto rounded-lg shadow-lg">
                        @else
                            <div class="text-6xl mb-4">📄</div>
                            <p class="text-gray-600">Non-image file selected</p>
                        @endif

                        <div class="bg-white p-4 rounded-lg mt-4 text-left">
                            <p class="text-sm text-gray-600"><strong>Name:</strong> {{ $file->getClientOriginalName() }}</p>
                            <p class="text-sm text-gray-600"><strong>Size:</strong> {{ number_format($file->getSize() / 1024, 2) }} KB</p>
                            <p class="text-sm text-gray-600"><strong>Type:</strong> {{ $file->getMimeType() }}</p>
                        </div>

                        <div class="mt-6 flex gap-4 justify-center">
                            <button 
                                type="submit" 
                                class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-6 rounded-lg transition"
                                wire:loading.attr="disabled"
                            >
                                Upload File
                            </button>
                            
                            <button 
                                type="button" 
                                wire:click="removeFile" data-confirm="Remove this file?"
                                class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-6 rounded-lg transition"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </form>
    </div>


    <form wire:submit="save">

        <div>
            <input type="text" wire:model="title">
            @error('title') <span class="error">{{ $message }}</span> @enderror
        </div>

        <!-- Content Editor -->
        <div class="mb-6" wire:ignore>
            <label class="block text-gray-700 text-sm font-bold mb-2">
                Content
            </label>
            <div id="editor" style="height: 400px;"></div>
            <input type="hidden" wire:model="content" id="content">
            @error('content') 
                <span class="text-red-500 text-xs italic mt-2 block">{{ $message }}</span> 
            @enderror
        </div>
        <div>
            <input type="file" wire:model="photo">        
            @error('photo') <span class="error">{{ $message }}</span> @enderror
        </div>
     
        <button class="p-5 bg-fuchsia-950 text-amber-50" type="submit">Save photo</button>
    </form>

    <style>
        [wire\:loading] {
            display: inline-block;
        }
    </style>



    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        document.addEventListener('livewire:init', () => {
            // Initialize Quill editor
            const quill = new Quill('#editor', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                        [{ 'font': [] }],
                        [{ 'size': ['small', false, 'large', 'huge'] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'script': 'sub'}, { 'script': 'super' }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'indent': '-1'}, { 'indent': '+1' }],
                        [{ 'direction': 'rtl' }],
                        [{ 'align': [] }],
                        ['blockquote', 'code-block'],
                        ['link', 'image', 'video'],
                        ['clean']
                    ]
                },
                placeholder: 'Write your content here...'
            });

            // Sync Quill content to Livewire
            quill.on('text-change', function() {
                let content = quill.root.innerHTML;
                @this.set('content', content);
            });

            // Listen for Livewire updates to sync content back to Quill
            Livewire.on('contentUpdated', (content) => {
                quill.root.innerHTML = content;
            });

            // Set initial content if exists
            @if($content)
                quill.root.innerHTML = @json($content);
            @endif
        });
</script>
    <script>
        const fileInput = document.getElementById('fileInput');
        const previewContainer = document.getElementById('previewContainer');
        const imagePreview = document.getElementById('imagePreview');
        const filePreview = document.getElementById('filePreview');
        const fileInfo = document.getElementById('fileInfo');

        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                previewContainer.classList.add('active');
                
                // Display file information
                const fileSize = (file.size / 1024).toFixed(2);
                fileInfo.innerHTML = `
                    <p><strong>Name:</strong> ${file.name}</p>
                    <p><strong>Size:</strong> ${fileSize} KB</p>
                    <p><strong>Type:</strong> ${file.type}</p>
                `;
                
                // Check if file is an image
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = 'block';
                        filePreview.style.display = 'none';
                    };
                    
                    reader.readAsDataURL(file);
                } else {
                    imagePreview.style.display = 'none';
                    filePreview.style.display = 'block';
                }
            }
        });

        function removeFile() {
            fileInput.value = '';
            previewContainer.classList.remove('active');
            imagePreview.src = '';
        }
    </script>
</div>
