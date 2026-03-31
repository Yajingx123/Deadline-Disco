import { useState } from 'react';
import { ALL_TAGS } from '../data/mockData';

export default function CreatePostModal({ onClose, onSubmit }) {
  const [title, setTitle] = useState('');
  const [author, setAuthor] = useState('');
  const [mediaType, setMediaType] = useState('link');
  const [selectedTags, setSelectedTags] = useState([]);

  // 弹窗里只展示非媒体类的普通标签供用户勾选
  const normalTags = ALL_TAGS.filter(tag => !['image', 'video', 'audio', 'link'].includes(tag));

  const handleTagToggle = (tag) => {
    if (selectedTags.includes(tag)) {
      setSelectedTags(selectedTags.filter(t => t !== tag));
    } else {
      setSelectedTags([...selectedTags, tag]);
    }
  };

  const handleSubmit = () => {
    if (!title.trim()) return alert("Title cannot be empty");
    if (!author.trim()) return alert("Author cannot be empty");
    onSubmit(title, selectedTags, author, mediaType);
    onClose();
  };

  return (
    <div className="modal-overlay">
      <div className="modal-content">
        <h3>Create New Post</h3>
        <input type="text" placeholder="Post Title..." value={title} onChange={(e) => setTitle(e.target.value)} />
        <input type="text" placeholder="Author Name..." value={author} onChange={(e) => setAuthor(e.target.value)} />
        
        <div>
          <label style={{fontWeight:'bold'}}>Media Type: </label>
          <select value={mediaType} onChange={(e) => setMediaType(e.target.value)} style={{padding: '5px'}}>
            <option value="image">Image</option>
            <option value="video">Video</option>
            <option value="audio">Audio</option>
            <option value="link">Link</option>
          </select>
        </div>

        <div>
          <h4>Select Category Tags:</h4>
          {normalTags.map(tag => (
            <label key={tag} style={{ marginRight: '10px' }}>
              <input type="checkbox" onChange={() => handleTagToggle(tag)} /> {tag}
            </label>
          ))}
        </div>

        <div style={{ display: 'flex', gap: '10px', marginTop: '15px' }}>
          <button onClick={handleSubmit} className="post-btn">Submit</button>
          <button onClick={onClose} style={{ padding: '8px 16px' }}>Cancel</button>
        </div>
      </div>
    </div>
  );
}