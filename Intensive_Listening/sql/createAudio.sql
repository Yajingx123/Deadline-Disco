-- auto-generated definition
create table intensive_listening_audio
(
    audio_id       int auto_increment comment '音频唯一标识（自增主键）'
        primary key,
    title          varchar(100)                       not null comment '音频标题（如 Daily Life in the UK）',
    duration       varchar(10)                        not null comment '音频时长（格式：分:秒，如 3:45）',
    difficulty     varchar(10)                        not null comment '难度等级（Easy/Medium/Hard）',
    description    text                               null comment '音频描述（长文本）',
    tags           varchar(255)                       null comment '标签集合（用逗号分隔，如 Daily Life,UK Culture,Beginner）',
    author         varchar(50)                        null comment '音频作者（如 By: Emma Thompson）',
    create_time    datetime default CURRENT_TIMESTAMP null comment '数据录入时间（自动填充）',
    path           varchar(255)                       null comment '存放音频字幕文件路径',
    sentence_count int                                not null
)
    comment '英语音频学习信息表';

INSERT INTO `intensive_listening_audio`
    (`title`, `duration`, `difficulty`, `description`, `tags`, `author`, `path`, `sentence_count`)
VALUES
    (
        'Daily Life in the UK',
        '2:19',
        'Easy',
        'Learn about daily routines and customs in the United Kingdom, including typical schedules, food, and social interactions.',
        'Daily Life,UK Culture,Beginner',
        'By: Emma Thompson',
        '1',
        12
    ),
    (
        'Business Meeting Essentials',
        '2:34',
        'Medium',
        'Learn key phrases and etiquette for business meetings in English, including introductions, agendas, and closing remarks.',
        'Business,Meetings,Intermediate',
        'By: John Smith',
        '1',
        22
    ),
    (
        'Environmental Conservation',
        '2:41',
        'Medium',
        'Explore topics related to environmental conservation, including climate change, renewable energy, and sustainable practices.',
        'Environment,Science,Intermediate',
        'By: Sarah Johnson',
        '1',
        17
    ),
    (
        'Advanced Academic Vocabulary',
        '2:18',
        'Hard',
        'Learn advanced academic vocabulary and expressions commonly used in university lectures and research papers.',
        'Academic,Vocabulary,Advanced',
        'By: Professor Robert Davis',
        '1',
        12
    );