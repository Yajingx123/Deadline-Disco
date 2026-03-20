-- auto-generated definition
CREATE TABLE IF NOT EXISTS user_audio_progress
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

DELETE FROM `user_audio_progress`;

INSERT INTO `user_audio_progress`
    (`user_id`, `audio_id`, `progress_percent`, `current_index`, `progress_data`, `status`)
VALUES
    (1, 1, 50, 2, '[1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]', 'in_progress'),
    (2, 2, 36, 7, '[1, 0, 1, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]', 'in_progress'),
    (1, 4, 0, 0, '[0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]', 'Not Started');
