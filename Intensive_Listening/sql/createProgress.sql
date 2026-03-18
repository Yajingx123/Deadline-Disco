-- auto-generated definition
create table user_audio_progress
(
    progress_id      int auto_increment comment '主键'
        primary key,
    user_id          int                                not null comment '用户ID',
    audio_id         int                                not null comment '音频ID',
    progress_percent int      default 0                 null comment '整体进度 0~100',
    current_index    int      default 0                 null comment '当前听到第几句',
    progress_data    json                               null comment '句子进度数组：[1,2,3,0,...]',
    update_time      datetime default CURRENT_TIMESTAMP null on update CURRENT_TIMESTAMP,
    status           varchar(250)                       not null comment '完成情况',
    constraint uk_user_audio
        unique (user_id, audio_id)
)
    comment '偷懒版：用户音频进度表（含句子理解状态）';

INSERT INTO `user_audio_progress`
    (`user_id`, `audio_id`, `progress_percent`, `current_index`, `progress_data`, `status`)
VALUES
    -- 第 1 条数据 (对应截图 ID 2)
    (2, 1, 50, 2, '[1, 2, 0, 0]', 'processing'),

    -- 第 2 条数据 (对应截图 ID 9)
    (9, 1, 80, 4, '[1, 1, 2, 1, 0]', 'processing'),

    -- 第 3 条数据 (对应截图 ID 14)
    (14, 1, 0, 0, '[0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]', 'Not Started');