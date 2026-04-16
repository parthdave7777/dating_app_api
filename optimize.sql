-- DATABSE OPTIMIZATON SCRIPT
-- RUN THIS TO FIX BOTTLENECKS IN CHAT AND DISCOVERY

-- 1. Messages table index for lightning fast chat history
CREATE INDEX idx_messages_match_id_id ON messages (match_id, id);

-- 2. Messages table index for status hashing (getReadHash)
CREATE INDEX idx_messages_match_status ON messages (match_id, sender_id, is_read, is_received, is_deleted, is_edited);

-- 3. Swipes table optimization for discovery loops
CREATE INDEX idx_swipes_swiper_target ON swipes (swiper_id, swiped_id);
CREATE INDEX idx_swipes_created_at ON swipes (created_at);

-- 4. Matches table for fast listing
CREATE INDEX idx_matches_users ON matches (user1_id, user2_id);

-- 5. User active tracking
CREATE INDEX idx_users_last_active ON users (last_active);
CREATE INDEX idx_users_updated_at ON users (updated_at);
